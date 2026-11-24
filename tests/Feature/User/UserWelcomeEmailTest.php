<?php

use App\Mail\UserWelcomeEmail;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

// Settings > Users: a new user is emailed how to sign in.
beforeEach(function () {
    Mail::fake();
    [$this->company, $this->branch] = createCompanyWithMainBranch(['client_code' => 'ACME1']);
    $this->admin = createUserWithRole('company_admin', $this->company, $this->branch);
    $this->admin->update(['name' => 'Grace Admin']);
    Sanctum::actingAs($this->admin->fresh(), ['*']);
});

it('emails a new user their client code, email, password and the sign-in steps', function () {
    $this->postJson('/api/v1/users', [
        'name' => 'Sam New', 'email' => 'sam@acme.test', 'password' => 'Welcome123', 'roles' => ['cashier'],
    ])->assertCreated()->assertJsonPath('data.welcome_email_sent', true);

    Mail::assertSent(UserWelcomeEmail::class, function (UserWelcomeEmail $mail) {
        $html = $mail->render();

        return $mail->hasTo('sam@acme.test')
            && $mail->envelope()->replyTo[0]->address === $this->admin->email
            && str_contains($html, 'ACME1')
            && str_contains($html, 'sam@acme.test')
            && str_contains($html, 'Welcome123')
            && str_contains($html, '/login')
            && str_contains($html, 'Grace Admin has created a FlipERP account for you')
            && str_contains($html, 'How to sign in');
    });
});

it('lets the new user sign in with exactly what the email gave them', function () {
    $this->postJson('/api/v1/users', ['name' => 'Sam New', 'email' => 'sam@acme.test', 'password' => 'Welcome123', 'roles' => ['cashier']])->assertCreated();

    $sent = null;
    Mail::assertSent(UserWelcomeEmail::class, function ($mail) use (&$sent) {
        return $sent = $mail;
    });

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/auth/login', ['client_code' => $sent->clientCode, 'email' => $sent->email, 'password' => $sent->password])->assertOk();
});

it('still creates the user when the email cannot be sent, and says so', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    $this->postJson('/api/v1/users', ['name' => 'Sam New', 'email' => 'sam@acme.test', 'password' => 'Welcome123'])
        ->assertCreated()
        ->assertJsonPath('data.welcome_email_sent', false);

    $this->assertDatabaseHas('users', ['email' => 'sam@acme.test']);
});

it('does not email anyone when a user is only updated', function () {
    $userId = $this->postJson('/api/v1/users', ['name' => 'Sam New', 'email' => 'sam@acme.test', 'password' => 'Welcome123'])->json('data.id');
    Mail::fake();

    $this->putJson("/api/v1/users/{$userId}", ['name' => 'Sam Renamed'])->assertOk();

    Mail::assertNothingSent();
});
