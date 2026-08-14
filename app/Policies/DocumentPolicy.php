<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Document;

class DocumentPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can('admin.documents.view');
    }

    public function verify(Admin $admin, Document $document): bool
    {
        return $admin->can('admin.documents.verify');
    }

    public function upload(Admin $admin): bool
    {
        return $admin->can('admin.documents.upload');
    }
}
