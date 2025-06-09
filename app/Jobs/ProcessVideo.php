<?php

namespace App\Jobs;

use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\WebM;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;

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
        if(!Redis::exists("video_processing:{$this->filename}")) {
            Redis::set("video_processing:{$this->filename}", true);

            $ffmpeg = FFMpeg::create();
            $format = new WebM('libvorbis', 'libvpx-vp9');
            $format->setKiloBitrate(1750);
            $format->setAdditionalParameters([
                '-crf', '34',
                '-cpu-used', '4',
                '-threads', '4',
            ]);

            $thumbnail = $ffmpeg->open(storage_path('app/private/' . $this->file));
            $thumbnail->frame(TimeCode::fromSeconds(1))
                ->save('/var/www/cdn.finobe.net/videos/thumbs/' . $this->thumbnail);
            $video = $ffmpeg->open(storage_path('app/private/' . $this->file));
            $video->save($format, '/var/www/cdn.finobe.net/videos/data/' . $this->filename);

            Redis::del("video_processing:{$this->filename}");
        }
    }
}
