<?php

use App\Models\SiteConfig;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;

    public string $firstName = '';
    public string $lastInitial = '';
    public string $bio = '';
    public string $phone = '';
    public $photo;
    public ?string $existingPhotoUrl = null;

    public function mount(): void
    {
        $this->authorize('edit-staff-bio');

        $user = Auth::user();
        $this->firstName = $user->staff_first_name ?? '';
        $this->lastInitial = $user->staff_last_initial ?? '';
        $this->bio = $user->staff_bio ?? '';
        $this->phone = $user->staff_phone ?? '';
        $this->existingPhotoUrl = $user->staffPhotoUrl();
    }

    public function save(): void
    {
        $this->authorize('edit-staff-bio');

        $this->validate([
            'firstName' => 'nullable|string|max:50',
            'lastInitial' => 'nullable|string|max:1|alpha',
            'bio' => 'nullable|string|max:2000',
            'phone' => ['nullable', 'string', 'min:10', 'max:30', 'regex:/^[\d\s\-\(\)\+\.]+$/'],
            'photo' => 'nullable|mimes:jpg,jpeg,png,gif,webp,heic,heif|max:' . SiteConfig::getValue('max_image_size_kb', '2048'),
        ]);

        $user = Auth::user();

        if ($this->photo) {
            $oldPath = $user->staff_photo_path;
            $path = $this->photo->store('staff-photos', config('filesystems.public_disk'));
            $user->staff_photo_path = $path;
        }

        $user->staff_first_name = $this->firstName ?: null;
        $user->staff_last_initial = $this->lastInitial ? strtoupper($this->lastInitial) : null;
        $user->staff_bio = $this->bio ?: null;
        $user->staff_phone = $this->phone ?: null;
        $user->save();

        // Delete old photo only after DB save succeeds
        if (isset($oldPath) && $oldPath) {
            Storage::disk(config('filesystems.public_disk'))->delete($oldPath);
        }

        $this->existingPhotoUrl = $user->staffPhotoUrl();
        $this->photo = null;

        Flux::toast('Staff bio updated successfully.', 'Saved', variant: 'success');
    }

    public function removeUploadedPhoto(): void
    {
        $this->photo = null;
    }

    public function with(): array
    {
        $maxKb = (int) SiteConfig::getValue('max_image_size_kb', '2048');

        return [
            'maxImageSizeLabel' => $maxKb >= 1024 ? round($maxKb / 1024) . 'MB' : $maxKb . 'KB',
        ];
    }

    public function removePhoto(): void
    {
        $this->authorize('edit-staff-bio');

        $user = Auth::user();
        if ($user->staff_photo_path) {
            $oldPath = $user->staff_photo_path;
            $user->update(['staff_photo_path' => null]);
            Storage::disk(config('filesystems.public_disk'))->delete($oldPath);
            $this->existingPhotoUrl = null;
        }
        Flux::toast('Photo removed.', 'Done', variant: 'success');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout heading="Staff Bio" subheading="Manage your public staff profile visible to the community and parents">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            @if($existingPhotoUrl)
                <div class="flex items-center gap-4">
                    <img src="{{ $existingPhotoUrl }}" alt="Staff photo" class="w-20 h-20 rounded-lg object-cover" />
                    <flux:button type="button" wire:click="removePhoto" variant="ghost" size="sm">Remove Photo</flux:button>
                </div>
            @endif

            <flux:file-upload wire:model="photo" label="Photo">
                <flux:file-upload.dropzone
                    heading="Drop a photo or click to browse"
                    :text="'JPG, PNG, GIF, WEBP, HEIC up to ' . $maxImageSizeLabel"
                />
            </flux:file-upload>
            @if($photo)
                <flux:file-item
                    :heading="$photo->getClientOriginalName()"
                    :image="$photo->temporaryUrl()"
                    :size="$photo->getSize()"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove wire:click="removeUploadedPhoto" />
                    </x-slot>
                </flux:file-item>
            @endif

            <flux:input wire:model="firstName" label="First Name" placeholder="e.g. Jonathan" />
            <flux:input wire:model="lastInitial" label="Last Initial" maxlength="1" placeholder="e.g. Z" />

            <flux:field>
                <flux:label>Bio</flux:label>
                <flux:description>A brief introduction about yourself, your interests, and your role.</flux:description>
                <flux:textarea wire:model="bio" rows="5" placeholder="Tell the community about yourself..." />
                @error('bio') <flux:error>{{ $message }}</flux:error> @enderror
            </flux:field>

            <flux:field>
                <flux:label>Phone Number</flux:label>
                <flux:description>This is protected information only visible to Officers and Board Members.</flux:description>
                <flux:input wire:model="phone" placeholder="e.g. (555) 123-4567" />
                @error('phone') <flux:error>{{ $message }}</flux:error> @enderror
            </flux:field>

            <flux:button variant="primary" type="submit">Save Staff Bio</flux:button>
        </form>
    </x-settings.layout>
</section>
