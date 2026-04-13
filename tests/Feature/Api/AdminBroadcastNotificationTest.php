<?php

use App\Mail\AdminBroadcastMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

it('broadcasts notification and queues emails to all users', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->postJson(route('admin.broadcast'), [
        'title'   => 'Mise à jour importante',
        'message' => 'Une nouvelle fonctionnalité est disponible.',
        'type'    => 'info',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.users_notified', 4)
        ->assertJsonPath('data.emails_queued', 4)
        ->assertJsonPath('data.emails_failed', 0);

    Mail::assertQueued(AdminBroadcastMail::class, 4);
});

it('inserts a DB notification for each user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->count(2)->create();

    $this->actingAs($admin)->postJson(route('admin.broadcast'), [
        'title'   => 'Alerte',
        'message' => 'Message test',
        'type'    => 'warning',
    ]);

    $this->assertDatabaseCount('user_notifications', 3);
    $this->assertDatabaseHas('user_notifications', [
        'title' => 'Alerte',
        'body'  => 'Message test',
        'type'  => 'warning',
    ]);
});

it('sends email with correct subject and view', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user  = User::factory()->create();

    $this->actingAs($admin)->postJson(route('admin.broadcast'), [
        'title'   => 'Nouveau badge débloqué',
        'message' => 'Félicitations !',
        'type'    => 'success',
    ]);

    Mail::assertQueued(AdminBroadcastMail::class, function (AdminBroadcastMail $mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->broadcastTitle === 'Nouveau badge débloqué'
            && $mail->broadcastMessage === 'Félicitations !'
            && $mail->notificationType === 'success';
    });
});

it('defaults type to info when not provided', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create();

    $this->actingAs($admin)->postJson(route('admin.broadcast'), [
        'title'   => 'Info',
        'message' => 'Message sans type',
    ]);

    $this->assertDatabaseHas('user_notifications', [
        'type'    => 'info',
        'channel' => 'email',
    ]);
});

it('rejects broadcast without required fields', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->postJson(route('admin.broadcast'), [])
        ->assertUnprocessable();

    Mail::assertNothingQueued();
});

it('rejects broadcast from non-admin user', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->postJson(route('admin.broadcast'), [
            'title'   => 'Test',
            'message' => 'Test',
        ])
        ->assertForbidden();

    Mail::assertNothingQueued();
});

it('rejects broadcast from unauthenticated request', function () {
    $this->postJson(route('admin.broadcast'), [
        'title'   => 'Test',
        'message' => 'Test',
    ])->assertUnauthorized();

    Mail::assertNothingQueued();
});
