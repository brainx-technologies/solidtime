<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\Role;
use App\Enums\Weekday;
use App\Filament\Resources\UserResource;
use App\Models\Organization;
use App\Models\User;
use App\Service\UserService;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): User
    {
        $assignOrganization = null;
        if (! empty($data['assign_organization_id'])) {
            $assignOrganization = Organization::query()->findOrFail($data['assign_organization_id']);
        }

        $userService = app(UserService::class);
        $user = $userService->createUser(
            $data['name'],
            $data['email'],
            $data['password_create'],
            $data['timezone'],
            Weekday::from($data['week_start']),
            $assignOrganization !== null ? $assignOrganization->currency : $data['currency'],
            verifyEmail: (bool) $data['is_email_verified'],
            assignOrganization: $assignOrganization,
            assignOrganizationRole: $assignOrganization !== null
                ? Role::from($data['assign_organization_role'])
                : Role::Employee,
        );

        return $user;
    }
}
