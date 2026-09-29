<?php

namespace Tests\Unit;

use App\Services\CompanyEmailDomain;
use Tests\TestCase;

class CompanyEmailDomainTest extends TestCase
{
    public function test_allows_llc_and_projects_domains(): void
    {
        $this->assertTrue(CompanyEmailDomain::allows('user@tanseeqllc.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@tanseeqprojects.com'));
        $this->assertTrue(CompanyEmailDomain::allows('User@TanseeqLLC.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@tanseeqinvestment.com'));
        $this->assertTrue(CompanyEmailDomain::allows('mammadhukani.s@proscapeuae.com'));
        $this->assertFalse(CompanyEmailDomain::allows('user@gmail.com'));
        $this->assertFalse(CompanyEmailDomain::allows('not-an-email'));
    }

    public function test_hint_lists_company_domains(): void
    {
        $hint = CompanyEmailDomain::hint();

        $this->assertStringContainsString('@tanseeqllc.com', $hint);
        $this->assertStringContainsString('@tanseeqprojects.com', $hint);
        $this->assertStringContainsString('@proscapeuae.com', $hint);
    }
}
