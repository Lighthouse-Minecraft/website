<?php

namespace App\Actions;

use App\Models\DisciplineReport;
use App\Models\DisciplineReportImage;
use App\Models\SiteConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class AttachDisciplineReportImages
{
    use AsAction;

    public function handle(DisciplineReport $report, array $files): void
    {
        if ($report->isPublished()) {
            throw new \RuntimeException('Cannot attach images to a published discipline report.');
        }

        $maxKb = SiteConfig::getValue('max_image_size_kb', '2048');

        foreach ($files as $file) {
            /** @var UploadedFile $file */
            $validator = Validator::make(
                ['file' => $file],
                ['file' => "mimes:jpg,jpeg,png,gif,webp|max:{$maxKb}"]
            );

            $validator->validate();

            $path = $file->store("report-evidence/{$report->id}", config('filesystems.public_disk'));

            DisciplineReportImage::create([
                'discipline_report_id' => $report->id,
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
            ]);
        }
    }
}
