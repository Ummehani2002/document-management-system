<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MicrosoftGraphPeopleService
{
    public function __construct(
        protected MicrosoftTokenService $tokens
    ) {
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    public function search(User $user, string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 2) {
            return [];
        }

        $results = [];
        $seen = [];

        foreach ($this->searchMicrosoftPeople($user, $query) as $item) {
            $email = strtolower($item['email']);
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $results[] = $item;
        }

        foreach ($this->searchLocalUsers($query) as $item) {
            $email = strtolower($item['email']);
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $results[] = $item;
        }

        return array_slice($results, 0, $limit);
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

        $escaped = str_replace(['"', '\\'], '', $query);

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->withHeaders(['ConsistencyLevel' => 'eventual'])
            ->get('https://graph.microsoft.com/v1.0/me/people', [
                '$search' => '"'.$escaped.'"',
                '$select' => 'displayName,scoredEmailAddresses,emailAddresses',
                '$top' => 10,
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

            $name = trim((string) ($person['displayName'] ?? ''));
            $emails = $this->emailsFromPerson($person);

            foreach ($emails as $email) {
                if (! $this->matchesQuery($query, $name, $email)) {
                    continue;
                }

                $items[] = [
                    'name' => $name !== '' ? $name : $email,
                    'email' => $email,
                ];

                break;
            }
        }

        return $items;
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
        $needle = '%'.$query.'%';

        return User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($builder) use ($needle) {
                $builder->where('email', 'like', $needle)
                    ->orWhere('name', 'like', $needle)
                    ->orWhere('username', 'like', $needle);
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
        $haystacks = [
            strtolower($name),
            strtolower($email),
            strtolower((string) strstr($email, '@', true)),
        ];

        $needle = strtolower($query);

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
