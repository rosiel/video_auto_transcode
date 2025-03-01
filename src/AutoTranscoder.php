<?php

namespace Drupal\video_auto_transcode;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Drupal\file\Entity\File;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video;


class AutoTranscoder {
  use StringTranslationTrait;
  const SOURCE_FIELD = 'field_media_video_file_1';
  const DESTINATION_FIELD = 'field_transcoded_files';

  public function auto_transcode(MediaInterface $media) {
    # Test that the media has both source and dest fields
    # If there is a file in the source field then transcode it
    # to the dest field. If not, throw an error.
    if ($media->hasField(self::SOURCE_FIELD)
      and $media->hasField(self::DESTINATION_FIELD)
      and !($media->get(self::SOURCE_FIELD)->isEmpty())
      and $media->get(self::DESTINATION_FIELD)->isEmpty()) {
      $files = $media->get(self::SOURCE_FIELD);
      foreach ($files->getIterator() as $fileItem) {
        # don't do this, use a queue instead.
        $file = File::load($fileItem->get('target_id')->getValue());
        $mp4_file = $this->transcode_file($file, 'mp4');
        $this->put_file_to_media($mp4_file, $media, self::DESTINATION_FIELD);
        $webm_file = $this->transcode_file($file, 'webm');
        $this->put_file_to_media($webm_file, $media, self::DESTINATION_FIELD);
      }
    }
  }

  private function put_file_to_media($filepath, $media, $fieldname) {
    $derivative_file = File::create([
      'filename' => basename($filepath),
      'uri' => $filepath,
      'status' => 1,
      'uid' => 1
    ]);
    $derivative_file->save();
    $media->get($fieldname)->appendItem($derivative_file->id());
  }

  public function transcode_file($file, $output = 'mp4')
  {
    # Get the filename .
    $path = $file->createFileURL(FALSE);
    if (substr($path, 0, 6) == '/sites') {
      $path = ltrim($path, '/');
    }
    # Throw it in double quotes to work with spaces in filenames.

    $path_parts = pathinfo($path);

    $ffmpeg = FFMpeg::create();
    $video = $ffmpeg->open($path);
    $video
      ->filters()
      ->synchronize();

    if ($output == 'tn') {
      $tn_filepath = implode(DIRECTORY_SEPARATOR, [$path_parts['dirname'], $path_parts['filename'] . '-tn.jpg']);
      $video
        ->frame(TimeCode::fromSeconds(1))
        ->save($tn_filepath);
      return $tn_filepath;
    }
    if ($output == 'mp4') {
      $mp4_filepath = implode(DIRECTORY_SEPARATOR, [$path_parts['dirname'], $path_parts['filename'] . '-mp4.mp4']);
      $video->save(new Video\X264(), $mp4_filepath);
      return $mp4_filepath;
    }
    if ($output == 'webm') {
      $webm_filepath = implode(DIRECTORY_SEPARATOR, [$path_parts['dirname'], $path_parts['filename'] . '-webm.webm']);
      $video->save(new Video\WebM(), $webm_filepath);
      return $webm_filepath;
    }
    return;
  }

}


