<?php

namespace App\Services;

class CompanyEmailDomain
{
    /**
     * @return list<string>
     */
    public static function domains(): array
    {
        $domains = config('dms.allowed_email_domains', []);
        if (! is_array($domains) || $domains === []) {
            $domains = ['tanseeqllc.com', 'tanseeqprojects.com', 'tanseeqinvestment.com', 'proscapeuae.com'];
        }

        $normalized = [];
        foreach ($domains as $domain) {
            $domain = strtolower(trim((string) $domain));
            $domain = ltrim($domain, '@');
            if ($domain !== '') {
                $normalized[$domain] = $domain;
            }
        }

        return array_values($normalized);
    }

    public static function allows(string $email): bool
    {
        $domain = self::domainFrom($email);

        return $domain !== null && in_array($domain, self::domains(), true);
    }

    public static function domainFrom(string $email): ?string
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false || $at === 0 || $at === strlen($email) - 1) {
            return null;
        }

        return substr($email, $at + 1);
    }

    public static function hint(): string
    {
        $labels = array_map(static fn (string $domain): string => '@'.$domain, self::domains());
        if ($labels === []) {
            return 'a company email';
        }
        if (count($labels) === 1) {
            return $labels[0];
        }

        $last = array_pop($labels);

        return implode(', ', $labels).' or '.$last;
    }

    public static function loginMessage(): string
    {
        return 'Sign in with a company Microsoft account ('.self::hint().').';
    }

    public static function shareMessage(): string
    {
        return 'You can only share to company email addresses ('.self::hint().').';
    }
}
