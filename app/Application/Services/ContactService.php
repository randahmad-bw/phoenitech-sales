<?php

namespace App\Application\Services;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Collection;

/**
 * Handles contact person CRUD operations scoped to a company.
 */
class ContactService
{
    /**
     * List all contacts for a given company.
     */
    public function listByCompany(int $companyId): Collection
    {
        return Contact::where('company_id', $companyId)->orderBy('name')->get();
    }

    /**
     * Create a new contact for a company.
     */
    public function create(int $companyId, array $data): Contact
    {
        $data['company_id'] = $companyId;
        return Contact::create($data);
    }

    /**
     * Update an existing contact record.
     */
    public function update(int $id, array $data): Contact
    {
        $contact = Contact::findOrFail($id);
        $contact->update($data);
        return $contact->fresh();
    }

    /**
     * Delete a contact record.
     */
    public function delete(int $id): bool
    {
        return Contact::findOrFail($id)->delete();
    }
}
