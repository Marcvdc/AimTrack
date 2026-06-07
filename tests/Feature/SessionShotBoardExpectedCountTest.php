<?php

use App\Jobs\AnalyzeTurnPhotoJob;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('uploadPhoto passes expectedShotCount to the analysis job', function () {
    Queue::fake();
    Storage::fake('private');

    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->set('currentTurnIndex', 0)
        ->set('expectedShotCount', 5)
        ->set('photo', UploadedFile::fake()->image('target.jpg'))
        ->call('uploadPhoto');

    Queue::assertPushed(AnalyzeTurnPhotoJob::class, function (AnalyzeTurnPhotoJob $job) {
        return $job->expectedShotCountForTest() === 5;
    });
});
