<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'default_locale' => $this->default_locale ?? 'en',
            'locales' => $this->locales ? explode(',', $this->locales) : [$this->default_locale ?? 'en'],

            // Preferences · General
            'copyright' => $this->copyright,
            'filing_info' => $this->filing_info,
            'timezone' => $this->timezone,
            'language_direction' => $this->language_direction,

            // Preferences · Logo & Favicon
            'logo_url' => $this->logo_url,
            'favicon_url' => $this->favicon_url,

            // Preferences · Custom Code
            'custom_header' => $this->custom_header,
            'custom_footer' => $this->custom_footer,

            // Preferences · SEO
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keywords' => $this->seo_keywords,
            'analytics_script' => $this->analytics_script,

            // Preferences · Contact
            'contact_address' => $this->contact_address,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'contact_text' => $this->contact_text,
        ];
    }
}
