<?php

namespace Aine\Installer\Helpers;

class PermissionsChecker
{
    /**
     * @var array
     */
    protected $results = [];

    /**
     * Set the result array permissions and errors.
     *
     * @return mixed
     */
    public function __construct()
    {
        $this->results['permissions'] = [];

        $this->results['errors'] = null;
    }

    /**
     * Check for the folders permissions.
     *
     * @param array $folders
     * @return array
     */
    public function check(array $folders)
    {
        foreach ($folders as $folder => $permission) {
            $octalOk = $this->getPermission($folder) >= $permission;
            $writable = $this->isActuallyWritable($folder);

            if ($octalOk && $writable) {
                $this->addFile($folder, $permission, true);
            } else {
                $this->addFileAndSetErrors($folder, $permission, false);
            }
        }

        return $this->results;
    }

    /**
     * Get a folder permission.
     *
     * @param $folder
     * @return string
     */
    private function getPermission($folder)
    {
        return substr(sprintf('%o', fileperms(base_path($folder))), -4);
    }

    /**
     * Perform a real write test against the folder.
     *
     * @param string $folder  Folder path relative to base_path().
     * @return bool
     */
    private function isActuallyWritable($folder)
    {
        $path = base_path($folder);

        if (! is_dir($path)) {
            return false;
        }

        if (! is_writable($path)) {
            return false;
        }

        $probe = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . '.aine_write_test_' . bin2hex(random_bytes(4));

        try {
            @touch($probe);
            $ok = file_exists($probe);
            @unlink($probe);
            return $ok;
        } catch (\Throwable $e) {
            @unlink($probe);
            return false;
        }
    }

    /**
     * Add the file to the list of results.
     *
     * @param $folder
     * @param $permission
     * @param $isSet
     */
    private function addFile($folder, $permission, $isSet)
    {
        array_push($this->results['permissions'], [
            'folder' => $folder,
            'permission' => $permission,
            'isSet' => $isSet,
        ]);
    }

    /**
     * Add the file and set the errors.
     *
     * @param $folder
     * @param $permission
     * @param $isSet
     */
    private function addFileAndSetErrors($folder, $permission, $isSet)
    {
        $this->addFile($folder, $permission, $isSet);

        $this->results['errors'] = true;
    }
}
