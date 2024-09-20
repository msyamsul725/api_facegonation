<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\FaceRecognitionService;

class FaceRecognitionController extends Controller
{
    public function checkFaceRecognition(Request $request)
    {
        // Validasi input
        $request->validate([
            'photo_attendance' => 'required|string',
            'photo_testing' => 'required|string',
        ]);
    
        // Variabel untuk menyimpan path file gambar
        $attendancePath = null;
        $testingPath = null;
    
        try {
            // Decode gambar base64
            $photoAttendanceBase64 = $request->input('photo_attendance');
            $photoTestingBase64 = $request->input('photo_testing');
    
            // Simpan gambar sementara dan ambil nama file
            $attendanceFilename = $this->saveTempImage($photoAttendanceBase64, 'attendance');
            $testingFilename = $this->saveTempImage($photoTestingBase64, 'testing');
    
            // Buat path lengkap untuk gambar
            $attendancePath = storage_path("app/public/$attendanceFilename");
            $testingPath = storage_path("app/public/$testingFilename");
    
            // Log paths
            Log::info("Attendance image path: $attendancePath");
            Log::info("Testing image path: $testingPath");
    
            // Panggil layanan pengenalan wajah
            $isRecognized = FaceRecognitionService::recognize($attendanceFilename, $testingFilename);
    
            // Hapus gambar sementara
            $this->deleteFile($attendancePath);
            $this->deleteFile($testingPath);
    
            if (!$isRecognized) {
                return response()->json(['message' => 'Photos do not match'], 400);
            }
    
            return response()->json(['message' => 'Photos match'], 200);
    
        } catch (\Exception $e) {
            // Hapus gambar jika ada kesalahan
            $this->deleteFile($attendancePath);
            $this->deleteFile($testingPath);

            // Tangani error
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    
    private function saveTempImage($photoBase64, $type)
    {
        // Pastikan base64 memiliki prefix yang benar
        if (!preg_match('/^data:image\/(\w+);base64,/', $photoBase64, $matches)) {
            // Jika tidak ada prefix, tambahkan prefix default untuk JPEG
            $photoBase64 = 'data:image/jpeg;base64,' . $photoBase64;
        }

        // Hapus prefix data base64 jika ada
        if (preg_match('/^data:image\/(\w+);base64,/', $photoBase64, $matches)) {
            $imageType = $matches[1];
            $photoBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $photoBase64);
            $photoBase64 = str_replace(' ', '+', $photoBase64);
            $imageData = base64_decode($photoBase64);

            if ($imageData === false) {
                throw new \Exception("Gagal mendekode base64 data.");
            }

            // Validasi tipe gambar
            $validImageTypes = ['png', 'jpg', 'jpeg', 'gif'];
            if (!in_array($imageType, $validImageTypes)) {
                throw new \Exception("Tipe gambar tidak didukung.");
            }

            // Buat nama file dan path
            $filename = $type . '_' . uniqid() . '.' . ($imageType === 'jpg' ? 'jpeg' : $imageType);
            $path = storage_path("app/public/$filename");

            // Simpan gambar sebagai file
            if (file_put_contents($path, $imageData) === false) {
                throw new \Exception("Gagal menyimpan file gambar.");
            }

            // Cek apakah file berhasil disimpan
            if (!file_exists($path)) {
                throw new \Exception("File $path tidak ditemukan setelah disimpan.");
            }

            return $filename;
        } else {
            throw new \Exception("Format base64 tidak valid.");
        }
    }

    private function deleteFile($path)
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public static function download($url, $filename = "")
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get($url);

        if ($filename == "") {
            $filename = time() . '_' . '.jpg';
        } else {
            $filename = $filename . '.jpg';
        }

        Storage::disk('public')->put($filename, $response->getBody());
        return $filename;
    }
}
