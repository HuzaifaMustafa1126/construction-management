<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform-admin:create {--email=} {--name=}';
    protected $description = 'Provision the first company-independent platform administrator';

    public function handle(): int
    {
        if (User::query()->where('is_platform_admin', true)->exists()) {
            $this->error('A platform administrator already exists. No users were changed.');
            return self::FAILURE;
        }
        if (! $this->confirm('This grants full platform access. Is this operator explicitly authorized?', false)) return self::FAILURE;
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $password = (string) $this->secret('Password (12+ characters, mixed case, number and symbol)');
        $confirmation = (string) $this->secret('Confirm password');
        $validator = Validator::make(compact('email','name','password','confirmation'), [
            'email'=>['required','email','max:255','unique:users,email'], 'name'=>['required','string','max:255'],
            'password'=>['required', Password::min(12)->mixedCase()->numbers()->symbols()], 'confirmation'=>['same:password'],
        ]);
        if ($validator->fails()) { foreach ($validator->errors()->all() as $error) $this->error($error); return self::FAILURE; }
        User::create(['name'=>$name,'email'=>$email,'password'=>Hash::make($password),'status'=>User::STATUS_ACTIVE])->forceFill(['is_platform_admin'=>true,'email_verified_at'=>now(),'company_id'=>null])->save();
        $this->info('Platform administrator created. No company users were modified.');
        return self::SUCCESS;
    }
}
