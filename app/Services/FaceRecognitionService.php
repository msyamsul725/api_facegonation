<?php

namespace App\Services;
use Illuminate\Support\Facades\Log;

class FaceRecognitionService
{
    public static function recognize($user_face, $user_uploaded_face)
    {
        $path = base_path('python_app/recognize.py');
        $known_image_path = base_path("storage/app/public/$user_face");
        $unknown_image_path = base_path("storage/app/public/$user_uploaded_face");
        
        $command = "python \"$path\" \"$known_image_path\" \"$unknown_image_path\"";
        
        Log::info("Command to execute: $command");

        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes);
        
        if (is_resource($process)) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
        
            fclose($pipes[1]);
            fclose($pipes[2]);
            $return_value = proc_close($process);
        
            Log::info("Python output: $stdout");
            Log::info("Python error output: $stderr");
            Log::info("Return value: $return_value");

            $stdout = trim($stdout);
            if (stripos($stdout, "true") !== false) {
                return true;
            } else {
                Log::error("Expected 'true' not found in output.");
                return false;
            }
        } else {
            Log::error("Failed to open process.");
            return false;
        }
    }
}
