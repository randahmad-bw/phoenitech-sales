<?php

namespace App\Application\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Employee;

/**
 * Handles global search across all domain entities.
 */
class SearchService
{
    /**
     * Search across companies, employees, contacts, and contracts. Returns top 5 per type.
     */
    public function search(string $query): array
    {
        $limit = 5;

        return [
            'companies' => Company::where('name', 'like', "%{$query}%")->limit($limit)->get(['id', 'name', 'activity']),
            'employees' => Employee::where('name', 'like', "%{$query}%")->limit($limit)->get(['id', 'name', 'email']),
            'contacts' => Contact::where('name', 'like', "%{$query}%")->orWhere('mobile', 'like', "%{$query}%")->limit($limit)->get(['id', 'name', 'mobile', 'company_id']),
            'contracts' => Contract::where('contract_number', 'like', "%{$query}%")->limit($limit)->get(['id', 'contract_number', 'status', 'company_id']),
        ];
    }
}
