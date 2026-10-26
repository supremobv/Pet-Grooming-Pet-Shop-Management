<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

Artisan::command('app:admin {email} {--name=Business Owner} {--generate : Generate a local development password file}', function () {
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Enter a valid email address.');

        return 1;
    }
    if ($this->option('generate') && ! app()->environment('local')) {
        $this->error('--generate is local development only.');

        return 1;
    }
    $password = $this->option('generate') ? bin2hex(random_bytes(10)).'Aa!7' : $this->secret('Password (12+ characters, upper/lowercase, number, symbol)');
    $validator = validator(['password' => $password], ['password' => ['required', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols()]]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    $u = User::where('email', $email)->first();
    if ($u && ! $this->confirm('This account exists. Reset its password and make it an active administrator?')) {
        return 1;
    }
    User::updateOrCreate(['email' => $email], ['name' => $this->option('name'), 'password' => $password, 'role' => 'admin', 'active' => true]);
    if ($u) {
        DB::table('sessions')->where('user_id', $u->id)->delete();
    }
    if ($this->option('generate')) {
        file_put_contents(storage_path('app/local-access.txt'), "LOCAL DEVELOPMENT ONLY\nEmail: $email\nPassword: $password\nURL: http://127.0.0.1:8000\nChange password from My account after sign-in.\n");
        $this->info('Credentials saved in storage/app/local-access.txt (ignored by Git).');
    }
    $this->info('Administrator ready.');
})->purpose('Create or recover an administrator without a public registration endpoint');
