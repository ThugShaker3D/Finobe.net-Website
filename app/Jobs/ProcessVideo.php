<?php

namespace App\Jobs;

use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $file;
    protected $filename;
    protected $thumbnail;

    /**
     * Create a new job instance.
     */
    public function __construct($file, $filename, $thumbnail)
    {
        $this->file = $file;
        $this->filename = $filename;
        $this->thumbnail = $thumbnail;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $ffmpeg = FFMpeg::create();
        $video = $ffmpeg->open(storage_path('app/private/' . $this->file));
        $format = new X264('aac', 'libx264');

        $video->save($format, '/var/www/cdn.finobe.net/videos/data/' . $this->filename);
        $video->frame(TimeCode::fromSeconds(1))
              ->save('/var/www/cdn.finobe.net/videos/thumbs/' . $this->thumbnail);
    }
}
