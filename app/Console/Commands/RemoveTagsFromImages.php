<?php

namespace App\Console\Commands;

use App\Models\ImageIndex;
use App\Models\Images;
use App\Models\User;
use App\Traits\Miscellaneous;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RemoveTagsFromImages extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:imageTags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to remove image tags from images';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->printHeading('Remove tags from images', '-', 30);
        $this->printLine('Enter tags separated by ",": ', 1);
        [$tagsToRemove] = $this->readInputFromCli(1);
        $tagsToRemove = explode(',', str_replace(['#', ' '], '', $tagsToRemove));
        $missingTags = array_filter($tagsToRemove, function ($tag) {
            return !ImageIndex::where('tag', $tag)->exists();
        });
        if (!empty($missingTags)) {
            $this->printLine('These tags are absent: ' . implode(', ', array_map(function ($tag) {
                return '#' . $tag;
            }, $missingTags)) . '. Remove them & proceed?', 2);
            if ($this->getConfirmation()) {
                $tagsToRemove = array_diff($tagsToRemove, $missingTags);
            } else {
                $this->printHeading('OPERATION ABORTED BY USER', '-', 25);
                return Command::FAILURE;
            }
        }
        if (!empty($tagsToRemove)) {
            $this->printLine('Enter image ids separated by "," or enter range [minId, maxId] (includes min & max ids): ', 2);
            [$input] = $this->readInputFromCli(1);
            if (str_starts_with($input, '[')) {
                // range
                if (!str_ends_with($input, ']')) {
                    throw new Exception('Invalid range, please close square bracket.');
                }
                $minMaxIds = explode(',', str_replace(' ', '', Str::between($input, '[', ']')));
                if ($minMaxIds[0] > $minMaxIds[1]) {
                    throw new Exception('Invalid range, min id must be less than max id');
                }
            } else {
                $imageIds = array_filter(explode(',', str_replace(' ', '', $input)), function ($value) {
                    return is_numeric($value) && $value == (int)$value;
                });
            }
            if (isset($minMaxIds) || (isset($imageIds) && !empty($imageIds))) {
                // domain selection code: start
                $this->printLine('Select domain (public/private):', 2);
                $choice = $this->readInputFromCli(1, [], function ($str) {
                    return strtolower($str) === 'public' || strtolower($str) === 'private';
                });
                $userId = null;
                if (reset($choice) === 'private') {
                    // authentication code: start
                    $this->printLine('Enter user email:', 3);
                    $email = $this->readInputFromCli(1, [], function ($str) {
                        return filter_var($str, FILTER_VALIDATE_EMAIL);
                    });
                    $email = reset($email);
                    if (!$email) {
                        $this->printLine('Invalid email entered. PROCESS ABORTED.', 3, true);
                        return Command::FAILURE;
                    }
                    $user = User::select(['id', 'password'])->where('email', $email)->first();
                    if (!$user) {
                        $this->printLine('User not found, PROCESS ABORTED.', 3, true);
                        return Command::FAILURE;
                    }
                    $this->printLine('Enter password:', 3);
                    if ($this->readAndComparePasswordInputFromCli($user->password)) {
                        $userId = $user->id;
                        $this->printLine('Authentication successful, user id: ' . $userId, 2, true);
                        unset($user);
                    } else {
                        $this->printLine('Invalid password. PROCESS ABORTED.', 3, true);
                        return Command::FAILURE;
                    }
                    // authentication code: end
                }
                // domain selection code: end
                $updateCount = 0;
                $unprocessableImageIds = [];
                if (isset($minMaxIds)) {
                    for ($imageId = $minMaxIds[0]; $imageId <= $minMaxIds[1]; $imageId++) {
                        $this->printLine('Processing for image id: ' . $imageId . ' ... ', 3, true);
                        $imageData = Images::select(['type', 'tags'])->where([
                            'id' => $imageId,
                            'user_id' => $userId
                        ])->first();
                        if ($imageData) {
                            if (substr_count($imageData->tags, '#') > 1 && substr_count($imageData->tags, ',') === 0) {
                                $unprocessableImageIds[] = $imageId;
                                $this->removeLastLine();
                                continue;
                            }
                            $presentTags = explode(',', str_replace(['#', ' '], '', $imageData->tags));
                            if (empty(array_intersect($presentTags, $tagsToRemove))) {
                                $this->removeLastLine();
                                continue;
                            }
                            $tagStr = implode(', ', array_map(function ($tag) {
                                return '#' . $tag;
                            }, array_diff($presentTags, $tagsToRemove)));
                            $update = Images::updateImageInfo([
                                'imageId' => $imageId,
                                'type' => $imageData->type,
                                'tags' => $tagStr
                            ], $userId);
                            if ($update) {
                                $updateCount++;
                            } else {
                                $unprocessableImageIds[] = $imageId;
                            }
                            $this->removeLastLine();
                        } else {
                            $unprocessableImageIds[] = $imageId;
                            $this->removeLastLine();
                        }
                    }
                } elseif (!empty($imageIds)) {
                    foreach ($imageIds as $imageId) {
                        $this->printLine('Processing for image id: ' . $imageId . ' ... ', 3, true);
                        $imageData = Images::select(['type', 'tags'])->where([
                            'id' => $imageId,
                            'user_id' => $userId
                        ])->first();
                        if ($imageData) {
                            if (substr_count($imageData->tags, '#') > 1 && substr_count($imageData->tags, ',') === 0) {
                                $unprocessableImageIds[] = $imageId;
                                $this->removeLastLine();
                                continue;
                            }
                            $presentTags = explode(',', str_replace(['#', ' '], '', $imageData->tags));
                            if (empty(array_intersect($presentTags, $tagsToRemove))) {
                                $this->removeLastLine();
                                continue;
                            }
                            $tagStr = implode(', ', array_map(function ($tag) {
                                return '#' . $tag;
                            }, array_diff($presentTags, $tagsToRemove)));
                            $update = Images::updateImageInfo([
                                'imageId' => $imageId,
                                'type' => $imageData->type,
                                'tags' => $tagStr
                            ], $userId);
                            if ($update) {
                                $updateCount++;
                            } else {
                                $unprocessableImageIds[] = $imageId;
                            }
                            $this->removeLastLine();
                        } else {
                            $unprocessableImageIds[] = $imageId;
                            $this->removeLastLine();
                        }
                    }
                }
                $this->printLine(number_format($updateCount) . ' images\' information updated.', 2, true);
                if (!empty($unprocessableImageIds)) {
                    $existingImageIds = Images::select('id')->whereIn('id', $unprocessableImageIds)
                        ->where('user_id', $userId)->pluck('id')->toArray();
                    if (!empty($existingImageIds)) {
                        $this->printLine(number_format(count($existingImageIds)) . ' images\' update failed.', 2, true);
                        $this->printLine('Failed image ids: ' . implode(', ', $existingImageIds), 2);
                    }
                }
            }
        }
        $this->printHeading('OPERATION COMPLETED', '-', 30);
        return Command::SUCCESS;
    }
}
