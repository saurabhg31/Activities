<?php

namespace App\Console\Commands;

use App\Models\ImageIndex;
use App\Models\Images;
use App\Models\ImageType;
use App\Models\MemoryRequirements;
use App\Models\User;
use Illuminate\Console\Command;
use App\Traits\Miscellaneous;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class UploadImagesViaCli extends Command
{
    use Miscellaneous;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to upload images via cli.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->printHeading('Uploading images process from directory via cli started.');
        $dir = storage_path('pics' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'import');
        $this->printLine('Directory: ' . $dir, 1, true);
        $imageFiles = array_filter(scandir($dir), function ($str) {
            return $str !== '.' && $str !== '..';
        });
        $totalImages = count($imageFiles);
        $this->printLine(number_format($totalImages) . ' files found. Proceed?', 1);
        if ($this->getConfirmation()) {
            $this->printLine('Select domain (public/private):', 1);
            $choice = $this->readInputFromCli(1, [], function ($str) {
                return strtolower($str) === 'public' || strtolower($str) === 'private';
            });
            $userId = null;
            if (reset($choice) === 'private') {
                $this->printLine('Enter user email:', 2);
                $email = $this->readInputFromCli(1, [], function ($str) {
                    return filter_var($str, FILTER_VALIDATE_EMAIL);
                });
                $email = reset($email);
                if (!$email) {
                    $this->printLine('Invalid email entered. PROCESS ABORTED.', 2, true);
                    return Command::FAILURE;
                }
                $user = User::select(['id', 'password'])->where('email', $email)->first();
                if (!$user) {
                    $this->printLine('User not found, PROCESS ABORTED.', 2, true);
                    return Command::FAILURE;
                }
                $this->printLine('Enter password:', 2);
                if ($this->readAndComparePasswordInputFromCli($user->password)) {
                    $userId = $user->id;
                    $this->printLine('Authentication successful, user id: ' . $userId, 1, true);
                    unset($user);
                } else {
                    $this->printLine('Invalid password. PROCESS ABORTED.', 2, true);
                    return Command::FAILURE;
                }
            }
            $this->printLine('Add image type:', 1);
            [$type] = $this->readInputFromCli(1, [], function ($str) {
                return !is_numeric($str);
            }, null, true);
            $type = strtoupper(trim($type));
            $this->printLine('Add image tags:', 1);
            [$tags] = $this->readInputFromCli(1);
            $tags = trim($tags);
            $this->printLine('Uploading ' . number_format($totalImages) . ' images, each image will be deleted after successful upload.', 1, true);
            $this->printLine('Image Type: ' . $type . ', Tags: ' . $tags, 2, true);
            $uploadedCount = 0;
            $imagesDataSizeInBytes = 0;
            $imageModel = new Images();
            $imageIndexModel = new ImageIndex();
            $this->addImageCategory($type, $userId);
            foreach ($imageFiles as $imageFileName) {
                $this->printLine('Uploading image: ' . $imageFileName . '. Progress: ' . $this->printProgressBar(($uploadedCount / $totalImages) * 100), 3);
                $filePath = $dir . DIRECTORY_SEPARATOR . $imageFileName;
                $imageData = array(
                    'type' => $type,
                    'image' => base64_encode(file_get_contents($filePath)),
                    'imageType' => File::extension($filePath),
                    'tags' => $tags,
                    'user_id' => $userId,
                    'created_at' => now()
                );
                if ($imageId = $imageModel->create($imageData)->id) {
                    $imagesDataSizeInBytes += File::size($filePath);
                    $imageIndexModel->addIndices($imageId, $tags);
                }
                unlink($filePath);
                $uploadedCount++;
                $this->printActionCompletedMsg();
                $this->removeLastLine();
            }
            $this->printLine('Adding memory requirements to table ... ', 2);
            if ($imagesDataSizeInBytes) {
                MemoryRequirements::appendExtraDataToRequirements($imagesDataSizeInBytes);
            }
            $this->printActionCompletedMsg();
            $this->printLine(number_format($uploadedCount) . ' images successfully uploaded to database.', 1);
        } else {
            $this->printLine('PROCESS ABORTED BY USER', 1);
        }
        $this->printHeading('OPERATION COMPLETED', '-', 15);
        // print(PHP_EOL);
        Artisan::call('process:images');
        Artisan::call('compress:images');
        Artisan::call('generate:imagesHash');
        return Command::SUCCESS;
    }

    private function addImageCategory(string $type, ?int $userId = null)
    {
        $data = [
            'type' => $type,
            'user_id' => $userId
        ];
        if (!ImageType::where($data)->exists()) {
            ImageType::create($data);
        }
    }
}
