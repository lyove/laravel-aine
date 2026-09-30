<?php

namespace App\Http\Controllers\Admin;

use App\Aine\AuditLogger;
use App\Aine\UploadGuard;
use App\Filesystem\MediaStorage;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class MediaLibraryController extends Controller
{

    /**
     * Get project files
     *
     * @param int $project_id
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function getFiles($project_id, Request $request){
        $project = Project::with('collections')->findOrFail($project_id);

        /** @var \App\Models\User $user */
        $this->authorize('manageMedia', $project);

        $data['project'] = $project;

        $data['media'] = Media::with('project')->where('project_id', $project->id)->when($request->get('search'), function($q)use($request){
            $searchItem = $request->get('search');
            return $q->where('name', 'LIKE', "%$searchItem%");
        })->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->paginate(24);

        $php_post_max_size = $this->return_bytes(ini_get('post_max_size'));
        $php_upload_max_filesize = $this->return_bytes(ini_get('upload_max_filesize'));
        $env_max_file_size = $this->maxUploadBytes();

        if($php_post_max_size < $php_upload_max_filesize){
            $max_file_size = $php_post_max_size;
        } else {
            if($php_upload_max_filesize < $env_max_file_size){
                $max_file_size = $php_upload_max_filesize;
            } else {
                $max_file_size = $env_max_file_size;
            }
        }

        $data['upload_max_filesize'] = $max_file_size;

        $setting = $this->setting();
        $data['media_storage_driver'] = MediaStorage::activeDriver();
        $data['media_enable_chunk_upload'] = (bool) ($setting->media_enable_chunk_upload ?? false);
        $data['media_chunk_size'] = $this->chunkSizeBytes();
        $data['media_oss_configured'] = $this->ossConfigured();

        return $data;
    }

    /**
     * Return size in bytes
     *
     * @param string $val
     * @return int $val
    */
    private function return_bytes ($val) {
        if(empty($val)) {
            return 0;
        }

        $val = trim($val);

        preg_match('#([0-9]+)[\s]*([a-z]+)#i', $val, $matches);

        $last = '';
        if(isset($matches[2])){
            $last = $matches[2];
        }

        if(isset($matches[1])){
            $val = (int) $matches[1];
        }

        switch (strtolower($last))
        {
            case 'g':
            case 'gb':
                $val *= 1024;
            case 'm':
            case 'mb':
                $val *= 1024;
            case 'k':
            case 'kb':
                $val *= 1024;
        }

        return (int) $val;
    }

    /**
     * Upload a file
     *
     * @param int $project_id
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\Media
     */
    public function upload($project_id, Request $request){
        $project = Project::findOrFail($project_id);

        /** @var \App\Models\User $user */
        $this->authorize('manageMedia', $project);

        if($request->has('file')){
            $this->assertValidFile($request->file('file'));

            return response($this->storeUploadedFile($project, $request->file('file')), 200);
        }
    }

    /**
     * Store a chunk of a file being uploaded with chunked upload enabled.
     *
     * @param int $project_id
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function uploadChunk($project_id, Request $request){
        $project = Project::findOrFail($project_id);

        /** @var \App\Models\User $user */
        $this->authorize('manageMedia', $project);

        $request->validate([
            'file' => 'required|file',
            'identifier' => 'required|string|max:64',
            'index' => 'required|integer|min:0',
        ]);

        if (!$this->chunkUploadEnabled()) {
            return response()->json(['error' => __('Chunk upload is disabled.')], 422);
        }

        $identifier = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->get('identifier'));
        $dir = 'chunks/'.$identifier;

        Storage::disk('local')->putFileAs($dir, $request->file('file'), (string) $request->get('index'));

        return response()->json(['success' => true]);
    }

    /**
     * Assemble uploaded chunks into the final file and store it.
     *
     * @param int $project_id
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\Media
     */
    public function uploadComplete($project_id, Request $request){
        $project = Project::findOrFail($project_id);

        /** @var \App\Models\User $user */
        $this->authorize('manageMedia', $project);

        $request->validate([
            'identifier' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'total' => 'required|integer|min:1|max:10000',
        ]);

        if (!$this->chunkUploadEnabled()) {
            return response()->json(['error' => __('Chunk upload is disabled.')], 422);
        }

        $identifier = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->get('identifier'));
        $total = (int) $request->get('total');
        $dir = 'chunks/'.$identifier;

        $tmpPath = tempnam(sys_get_temp_dir(), 'ainechunk_');

        try {
            for ($i = 0; $i < $total; $i++) {
                if (!Storage::disk('local')->exists($dir.'/'.$i)) {
                    Storage::disk('local')->deleteDirectory($dir);

                    return response()->json(['error' => __('Chunk :index is missing.', ['index' => $i + 1])], 422);
                }

                file_put_contents($tmpPath, Storage::disk('local')->get($dir.'/'.$i), FILE_APPEND);
            }

            Storage::disk('local')->deleteDirectory($dir);

            $file = new UploadedFile($tmpPath, $request->get('name'), null, null, true);

            $this->assertValidFile($file);

            return response($this->storeUploadedFile($project, $file), 200);
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Validate an uploaded file against the active media settings and reject
     * dangerous payloads.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return void
     */
    private function assertValidFile(UploadedFile $file){
        $validator = Validator::make(
            ['file' => $file],
            ['file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,bmp,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,tar,gz,mp3,wav,ogg,mp4,webm,mov,avi,json',
                'max:'.($this->maxUploadBytes() / 1024),
            ]],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        UploadGuard::rejectDangerous($file);
    }

    /**
     * Persist an uploaded file: store the original, generate thumbnails at
     * every configured size and create the Media record.
     *
     * @param \App\Models\Project $project
     * @param \Illuminate\Http\UploadedFile $file
     * @return \App\Models\Media
     */
    private function storeUploadedFile(Project $project, UploadedFile $file){
        $file_name = $this->renameFile($file->getClientOriginalName(), $project->uuid, $file, $this->activeDisk($project));

        $disk = $this->activeDisk($project);
        $storagePath = $disk === 'oss' ? $project->uuid : ($project->disk === 'public' ? $project->uuid : 'public/'.$project->uuid);

        $this->mediaDisk($disk)->putFileAs($storagePath, $file, $file_name);

        $extension = $file->getClientOriginalExtension();

        $image_types = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp'];
        if(in_array($extension, $image_types)){
            $sizes = $this->thumbnailSizes();
            $primary = (int) $sizes[0];

            $manager = new ImageManager(new GdDriver());
            $thumb = $manager->read($file)->scale(height: $primary)->encodeByExtension($extension);

            $this->mediaDisk($disk)->put($storagePath.'/thumbnails/'.$file_name, $thumb);

            foreach (array_slice($sizes, 1) as $size) {
                $resized = $manager->read($file)->scale(height: (int) $size)->encodeByExtension($extension);
                $this->mediaDisk($disk)->put($storagePath.'/thumbnails/'.$size.'/'.$file_name, $resized);
            }
        }

        $image = @getimagesize($file);
        if ($image === false) {
            $image = null;
        }

        $new_file = Media::create([
            'project_id' => $project->id,
            'name' => $file_name,
            'type' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'width' => isset($image[0]) ? $image[0] : null,
            'height' => isset($image[1]) ? $image[1] : null,
            'disk' => $disk === 'oss' ? 'oss' : $project->disk,
        ]);

        AuditLogger::log('upload', 'media', $new_file->id, $new_file->name, null, $project->id);

        return $new_file;
    }

    /**
     * Rename a file
     *
     * @param string $file_name
     * @param string $project_uuid
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $disk
     * @return string $file_name
     */
    private function renameFile($file_name, $project_uuid, $file, $disk){
        $storagePath = $disk === 'oss' ? $project_uuid : ($disk === 'public' ? $project_uuid : 'public/'.$project_uuid);

        $path = $storagePath.'/'.$file_name;

        $i = 1;
        while($this->mediaDisk($disk)->exists($path)){
            $name = explode('.', $file->getClientOriginalName());
            $file_name = $name[0] . '('. $i .')' . '.' . $file->getClientOriginalExtension();
            $path = $storagePath.'/'.$file_name;
            $i++;
        }

        return $file_name;
    }

    /**
     * Max upload size in bytes from the global Media setting (MB) or the
     * uploads config fallback.
     */
    private function maxUploadBytes(): int
    {
        $setting = $this->setting();

        if (!empty($setting->media_max_upload_size)) {
            return (int) $setting->media_max_upload_size * 1024 * 1024;
        }

        $value = config('uploads.max_file_size', '8M');
        $unit = strtoupper(substr((string) $value, -1));
        $size = (int) $value;

        return match ($unit) {
            'G' => $size * 1024 * 1024 * 1024,
            'M' => $size * 1024 * 1024,
            'K' => $size * 1024,
            default => max((int) $value, 1),
        };
    }

    /**
     * Comma-separated thumbnail heights from the global Media setting.
     *
     * @return int[]
     */
    private function thumbnailSizes(): array
    {
        $setting = $this->setting();
        $raw = trim((string) ($setting->media_thumbnail_sizes ?? '600'));

        $sizes = array_values(array_filter(array_map(
            static fn ($part) => (int) trim($part),
            explode(',', $raw)
        )));

        return $sizes !== [] ? $sizes : [600];
    }

    /**
     * Whether chunked upload is enabled by the global Media setting.
     */
    private function chunkUploadEnabled(): bool
    {
        return (bool) ($this->setting()->media_enable_chunk_upload ?? false);
    }

    /**
     * Fixed chunk size in bytes used by the front-end uploader.
     */
    private function chunkSizeBytes(): int
    {
        return 2 * 1024 * 1024;
    }

    /**
     * Active storage disk for media uploads: the configured cloud driver when
     * its credentials are present, the project disk otherwise.
     */
    private function activeDisk(Project $project): string
    {
        $driver = MediaStorage::activeDriver();

        if ($driver !== 'local' && MediaStorage::isConfigured($driver)) {
            return $driver;
        }

        return $project->disk;
    }

    /**
     * Build the storage instance for a disk, resolving dynamic credentials
     * for object-storage drivers (configured from the UI).
     */
    private function mediaDisk(string $disk): mixed
    {
        return MediaStorage::disk($disk);
    }

    /**
     * Whether the active global driver is a cloud provider with credentials.
     */
    private function ossConfigured(): bool
    {
        $driver = MediaStorage::activeDriver();

        return $driver !== 'local' && MediaStorage::isConfigured($driver);
    }

    /**
     * First settings row.
     */
    private function setting(): ?Setting
    {
        return Setting::first();
    }

    /**
     * Delete a file
     *
     * @param int $project_id
     * @param int $file_id
     * @return \Illuminate\Http\Response
     */
    public function delete($project_id, $file_id, Request $request){
        $project = Project::findOrFail($project_id);

        /** @var \App\Models\User $user */
        $this->authorize('manageMedia', $project);

        $file = Media::where('project_id', $project->id)->where('id', $file_id)->firstOrFail();

        $this->deleteMediaFiles($project, $file);

        $file->delete();

        AuditLogger::log('delete', 'media', $file_id, $file->name ?? null, null, $project->id);

        return response([], 200);
    }

    /**
     * Delete multiple files
     *
     * @param int $project_id
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function deleteSelected($project_id, Request $request){
        $project = Project::findOrFail($project_id);

        /** @var \App\Models\User $user */
        $this->authorize('manageMedia', $project);

        foreach ($request->get('files') as $file) {
            $file = Media::where('project_id', $project->id)->where('id', $file)->first();

            if($file){
                $this->deleteMediaFiles($project, $file);
                $file->delete();
            }
        }

        AuditLogger::log('delete_selected', 'media', null, null, ['count' => count($request->get('files') ?? [])], $project->id);
    }

    /**
     * Remove the original and every thumbnail variant of a media record.
     *
     * @param \App\Models\Project $project
     * @param \App\Models\Media $file
     * @return void
     */
    private function deleteMediaFiles(Project $project, Media $file){
        $storagePath = $file->disk === 'oss' ? $project->uuid : ($file->disk === 'public' ? $project->uuid : 'public/'.$project->uuid);

        $original = $storagePath.'/'.$file->name;
        if($this->mediaDisk($file->disk)->exists($original)){
            $this->mediaDisk($file->disk)->delete($original);
        }

        $thumb = $storagePath.'/thumbnails/'.$file->name;
        if($this->mediaDisk($file->disk)->exists($thumb)){
            $this->mediaDisk($file->disk)->delete($thumb);
        }

        foreach ($this->thumbnailSizes() as $size) {
            $variant = $storagePath.'/thumbnails/'.$size.'/'.$file->name;
            if($this->mediaDisk($file->disk)->exists($variant)){
                $this->mediaDisk($file->disk)->delete($variant);
            }
        }
    }

    /**
     * Update a file
     *
     * @param int project_id
     * @param int file_id
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\Media
     */
    public function update($project_id, $file_id, Request $request){
        $project = Project::findOrFail($project_id);

        /** @var \App\Models\User $user */
        $this->authorize('manageMedia', $project);

        $media = Media::where('project_id', $project->id)->where('id', $file_id)->firstOrFail();

        $storagePath = $media->disk === 'oss' ? $project->uuid : ($media->disk === 'public' ? $project->uuid : 'public/'.$project->uuid);

        $path = $storagePath.'/'.$media->name;

        $ext = pathinfo($path, PATHINFO_EXTENSION);

        if($media->name == $request->name.'.'.$ext){
            $media->caption = $request->get('caption');
            $media->save();

            AuditLogger::log('update', 'media', $media->id, $media->name, null, $project->id);

            return response($media, 200);
        } else {
            $old_path = $storagePath.'/'.$media->name;
            $ext = pathinfo($old_path, PATHINFO_EXTENSION);

            $file_name = $request->get('name').'.'.$ext;
            $new_path = $storagePath.'/'.$file_name;
            $name = pathinfo($new_path, PATHINFO_FILENAME);

            $i = 1;
            while($this->mediaDisk($media->disk)->exists($new_path)){
                $file_name = $name . '('. $i .')' . '.' . $ext;
                $new_path = $storagePath.'/'.$file_name;
                $i++;
            }

            $old_thumb_path = $storagePath.'/thumbnails/'.$media->name;
            $this->mediaDisk($media->disk)->move($old_path, $new_path);

            $image_types = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'webp'];
            if(in_array($ext, $image_types)){
                $new_thumb_path = $storagePath.'/thumbnails/'.$file_name;
                $this->mediaDisk($media->disk)->move($old_thumb_path, $new_thumb_path);

                foreach ($this->thumbnailSizes() as $size) {
                    $old_variant = $storagePath.'/thumbnails/'.$size.'/'.$media->name;
                    $new_variant = $storagePath.'/thumbnails/'.$size.'/'.$file_name;
                    if($this->mediaDisk($media->disk)->exists($old_variant)){
                        $this->mediaDisk($media->disk)->move($old_variant, $new_variant);
                    }
                }
            }

            $media->name = $file_name;
            $media->caption = $request->get('caption');
            $media->save();

            AuditLogger::log('update', 'media', $media->id, $media->name, null, $project->id);

            return response($media, 200);
        }
    }
}
