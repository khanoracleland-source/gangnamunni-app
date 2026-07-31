<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateGangnamunniAdmin extends Command
{
    protected $signature = 'gangnamunni:admin
        {--username=admin : Administrator login}
        {--name=Super Admin : Display name}
        {--email= : Administrator email}
        {--role=super_admin : Administrator role}
        {--password= : Password; omit to enter it securely}';

    protected $description = 'Create or update a Gangnamunni administrator account';

    public function handle(): int
    {
        $table = 'gangnamunni_admins';
        if (! Schema::hasTable($table)) {
            $this->error("Table {$table} does not exist on the configured database.");

            return self::FAILURE;
        }

        $username = trim((string) $this->option('username'));
        $name = trim((string) $this->option('name'));
        $email = trim((string) $this->option('email'));
        $role = trim((string) $this->option('role'));
        $allowedRoles = [
            'super_admin', 'admin', 'editor', 'viewer', 'staff',
            'hospital_super_admin', 'hospital_admin', 'hospital_sub_admin', 'hospital_staff',
        ];

        if (! preg_match('/^[A-Za-z0-9_.@-]{3,100}$/', $username)) {
            $this->error('Username must be 3-100 characters and use only letters, numbers, . _ @ or -.');

            return self::FAILURE;
        }
        if (! in_array($role, $allowedRoles, true)) {
            $this->error('Unsupported administrator role.');

            return self::FAILURE;
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email address is invalid.');

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        if ($password === '') {
            $password = (string) $this->secret('Password');
            $confirmation = (string) $this->secret('Confirm password');
            if (! hash_equals($password, $confirmation)) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }
        if ($password === '') {
            $this->error('Password cannot be empty.');

            return self::FAILURE;
        }

        $values = [
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $name !== '' ? $name : $username,
            'role' => $role,
        ];
        $optionalValues = [
            'email' => $email,
            'phone' => '',
            'permissions' => json_encode(new \stdClass()),
            'is_active' => 1,
            'updated_at' => now(),
        ];
        foreach ($optionalValues as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $values[$column] = $value;
            }
        }

        $existing = DB::table($table)->where('username', $username)->exists();
        if ($existing) {
            if (! $this->confirm("Administrator '{$username}' exists. Update its password and role?", false)) {
                $this->warn('No changes were made.');

                return self::SUCCESS;
            }
            DB::table($table)->where('username', $username)->update($values);
            $this->info("Administrator '{$username}' was updated.");
        } else {
            $values['username'] = $username;
            if (Schema::hasColumn($table, 'created_at')) {
                $values['created_at'] = now();
            }
            DB::table($table)->insert($values);
            $this->info("Administrator '{$username}' was created.");
        }

        $this->line("Role: {$role}");
        $this->line('Login page: hairwang_full/gangnamunni/dashboard.php');

        return self::SUCCESS;
    }
}
