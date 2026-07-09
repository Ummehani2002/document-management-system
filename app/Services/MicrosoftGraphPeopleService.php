<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MicrosoftGraphPeopleService
{
    protected ?int $lastDirectoryStatus = null;

    public function __construct(
        protected MicrosoftTokenService $tokens
    ) {
    }

    /**
     * @return array{suggestions: list<array{name: string, email: string, group: string}>, hint: string|null}
     */
    public function searchWithMeta(User $user, string $query, int $limit = 12): array
    {
        $suggestions = $this->search($user, $query, $limit);
        $hint = null;

        if ($suggestions === []) {
            if ($this->tokens->accessToken($user) === null) {
                $hint = 'Company directory search needs Microsoft mail permission. Click Share once and approve access, then try again.';
            } elseif ($this->lastDirectoryStatus === 403) {
                $hint = 'Company directory search is not allowed yet. Ask your IT admin to add User.ReadBasic.All and grant admin consent in Azure, then open Share and approve permissions again.';
            } else {
                $hint = 'No matches in your company directory. Keep typing the full email address.';
            }
        }

        return [
            'suggestions' => $suggestions,
            'hint' => $hint,
        ];
    }

    public function hasDirectoryAccess(User $user): bool
    {
        return $this->tokens->accessToken($user) !== null;
    }

    /**
     * @return list<array{name: string, email: string, group: string}>
     */
    public function search(User $user, string $query, int $limit = 12): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 1) {
            return [];
        }

        $recent = [];
        $other = [];
        $seen = [];

        foreach ($this->recentMicrosoftPeople($user, $query) as $item) {
            $email = strtolower($item['email']);
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $recent[] = [
                'name' => $item['name'],
                'email' => $email,
                'group' => 'recent',
            ];
        }

        foreach ($this->searchMicrosoftPeople($user, $query) as $item) {
            $email = strtolower($item['email']);
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $other[] = [
                'name' => $item['name'],
                'email' => $email,
                'group' => 'other',
            ];
        }

        foreach ($this->searchDirectoryUsers($user, $query) as $item) {
            $email = strtolower($item['email']);
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $other[] = [
                'name' => $item['name'],
                'email' => $email,
                'group' => 'other',
            ];
        }

        foreach ($this->searchLocalUsers($query) as $item) {
            $email = strtolower($item['email']);
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $other[] = [
                'name' => $item['name'],
                'email' => $email,
                'group' => 'other',
            ];
        }

        return array_slice(array_merge($recent, $other), 0, $limit);
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    protected function recentMicrosoftPeople(User $user, string $query): array
    {
        $accessToken = $this->tokens->accessToken($user);
        if ($accessToken === null) {
            return [];
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://graph.microsoft.com/v1.0/me/people', [
                '$top' => 50,
                '$select' => 'displayName,scoredEmailAddresses,emailAddresses',
            ]);

        if (! $response->successful()) {
            return [];
        }

        $items = [];

        foreach ($response->json('value') ?? [] as $person) {
            if (! is_array($person)) {
                continue;
            }

            $parsed = $this->personToContact($person, $query);
            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        return array_slice($items, 0, 6);
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    protected function searchMicrosoftPeople(User $user, string $query): array
    {
        $accessToken = $this->tokens->accessToken($user);
        if ($accessToken === null) {
            return [];
        }

        $escaped = $this->escapeSearchTerm($query);

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->withHeaders(['ConsistencyLevel' => 'eventual'])
            ->get('https://graph.microsoft.com/v1.0/me/people', [
                '$search' => '"'.$escaped.'"',
                '$select' => 'displayName,scoredEmailAddresses,emailAddresses',
                '$top' => 15,
                '$count' => 'true',
            ]);

        if (! $response->successful()) {
            Log::warning('Microsoft Graph people search failed', [
                'user_id' => $user->id,
                'query' => $query,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $items = [];

        foreach ($response->json('value') ?? [] as $person) {
            if (! is_array($person)) {
                continue;
            }

            $parsed = $this->personToContact($person, $query, false);
            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        return $items;
    }

    /**
     * Search the organization directory (all users in the tenant).
     *
     * @return list<array{name: string, email: string}>
     */
    protected function searchDirectoryUsers(User $user, string $query): array
    {
        $accessToken = $this->tokens->accessToken($user);
        if ($accessToken === null) {
            return [];
        }

        $this->lastDirectoryStatus = null;
        $escaped = $this->escapeSearchTerm($query);
        $headers = ['ConsistencyLevel' => 'eventual'];

        $searchClauses = [
            'displayName:'.$escaped,
            'givenName:'.$escaped,
            'surname:'.$escaped,
            'mail:'.$escaped,
            'userPrincipalName:'.$escaped,
        ];
        $searchExpression = implode(' OR ', array_map(
            fn (string $clause) => '"'.$clause.'"',
            $searchClauses
        ));

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->withHeaders($headers)
            ->get('https://graph.microsoft.com/v1.0/users', [
                '$search' => $searchExpression,
                '$select' => 'displayName,mail,userPrincipalName,givenName,surname',
                '$top' => 15,
                '$count' => 'true',
            ]);

        if (! $response->successful()) {
            $this->lastDirectoryStatus = $response->status();
            $response = $this->filterDirectoryUsers($accessToken, $escaped, $headers);
        }

        if (! $response->successful()) {
            $this->lastDirectoryStatus = $response->status();
            Log::warning('Microsoft Graph directory user search failed', [
                'user_id' => $user->id,
                'query' => $query,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $items = [];

        foreach ($response->json('value') ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $parsed = $this->directoryUserToContact($row);
            if ($parsed !== null && $this->matchesQuery($query, $parsed['name'], $parsed['email'])) {
                $items[] = $parsed;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function filterDirectoryUsers(string $accessToken, string $escaped, array $headers): \Illuminate\Http\Client\Response
    {
        $prefixes = array_values(array_unique([
            $escaped,
            mb_strtolower($escaped),
            mb_convert_case($escaped, MB_CASE_TITLE),
        ]));

        $filterParts = [];
        foreach ($prefixes as $prefix) {
            $safe = str_replace("'", "''", $prefix);
            foreach (['displayName', 'givenName', 'surname', 'mail', 'userPrincipalName'] as $field) {
                $filterParts[] = "startswith({$field},'{$safe}')";
            }
        }

        return Http::withToken($accessToken)
            ->acceptJson()
            ->withHeaders($headers)
            ->get('https://graph.microsoft.com/v1.0/users', [
                '$filter' => implode(' or ', $filterParts),
                '$select' => 'displayName,mail,userPrincipalName,givenName,surname',
                '$top' => 15,
                '$count' => 'true',
            ]);
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array{name: string, email: string}|null
     */
    protected function personToContact(array $person, string $query, bool $requireMatch = true): ?array
    {
        $name = trim((string) ($person['displayName'] ?? ''));
        $emails = $this->emailsFromPerson($person);

        foreach ($emails as $email) {
            if ($requireMatch && ! $this->matchesQuery($query, $name, $email)) {
                continue;
            }

            return [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{name: string, email: string}|null
     */
    protected function directoryUserToContact(array $row): ?array
    {
        $name = trim((string) ($row['displayName'] ?? ''));
        $email = strtolower(trim((string) ($row['mail'] ?? '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower(trim((string) ($row['userPrincipalName'] ?? '')));
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return [
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
        ];
    }

    /**
     * @param  array<string, mixed>  $person
     * @return list<string>
     */
    protected function emailsFromPerson(array $person): array
    {
        $emails = [];

        foreach ($person['scoredEmailAddresses'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $address = strtolower(trim((string) ($entry['address'] ?? '')));
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $address;
            }
        }

        foreach ($person['emailAddresses'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $address = strtolower(trim((string) ($entry['address'] ?? '')));
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $address;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    protected function searchLocalUsers(string $query): array
    {
        $needle = '%'.mb_strtolower($query).'%';

        return User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($builder) use ($needle) {
                $builder->whereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(username) LIKE ?', [$needle]);
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['name', 'email'])
            ->map(function (User $user) {
                $email = strtolower(trim((string) $user->email));

                return [
                    'name' => trim((string) ($user->name ?: $email)),
                    'email' => $email,
                ];
            })
            ->filter(fn (array $item) => $item['email'] !== '' && filter_var($item['email'], FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }

    protected function matchesQuery(string $query, string $name, string $email): bool
    {
        $needle = mb_strtolower($query);
        $haystacks = [
            mb_strtolower($name),
            mb_strtolower($email),
            mb_strtolower((string) strstr($email, '@', true)),
        ];

        foreach (preg_split('/\s+/', mb_strtolower($name)) ?: [] as $part) {
            if ($part !== '') {
                $haystacks[] = $part;
            }
        }

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function escapeSearchTerm(string $query): string
    {
        return str_replace(['"', '\\'], '', trim($query));
    }
}
