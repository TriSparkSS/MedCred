<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Document;
use App\Services\AdminScopeService;

class DocumentPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('admin.documents.view');
    }

    public function view(Admin $admin, Document $document): bool
    {
        return $admin->can('admin.documents.view')
            && app(AdminScopeService::class)->canAccessDocument($admin, $document);
    }

    public function update(Admin $admin, Document $document): bool
    {
        return $admin->can('admin.documents.upload')
            && app(AdminScopeService::class)->canAccessDocument($admin, $document);
    }

    public function delete(Admin $admin, Document $document): bool
    {
        return ($admin->can('admin.documents.upload') || $admin->can('admin.documents.delete'))
            && app(AdminScopeService::class)->canAccessDocument($admin, $document);
    }

    public function verify(Admin $admin, Document $document): bool
    {
        return $admin->can('admin.documents.verify')
            && app(AdminScopeService::class)->canAccessDocument($admin, $document);
    }

    public function upload(Admin $admin): bool
    {
        return $admin->can('admin.documents.upload');
    }
}
