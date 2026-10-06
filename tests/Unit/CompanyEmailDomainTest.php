<?php

namespace Tests\Unit;

use App\Services\CompanyEmailDomain;
use Tests\TestCase;

class CompanyEmailDomainTest extends TestCase
{
    public function test_allows_group_company_domains(): void
    {
        $this->assertTrue(CompanyEmailDomain::allows('user@tanseeqllc.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@tanseeqprojects.com'));
        $this->assertTrue(CompanyEmailDomain::allows('User@TanseeqLLC.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@tanseeqinvestment.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@tanseeqrealty.com'));
        $this->assertTrue(CompanyEmailDomain::allows('mammadhukani.s@proscapeuae.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@proscapeksa.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@proscapefm.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@metalinellc.com'));
        $this->assertTrue(CompanyEmailDomain::allows('mubarak.ali@waterinmotionllc.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@ventanallc.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@acaciagardencenter.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@acacia-ae.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@timbertechllc.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@transmechllc.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@stonesandslates.com'));
        $this->assertTrue(CompanyEmailDomain::allows('user@bioscape.ae'));
        $this->assertFalse(CompanyEmailDomain::allows('user@gmail.com'));
        $this->assertFalse(CompanyEmailDomain::allows('not-an-email'));
    }

    public function test_hint_mentions_approved_company_domains(): void
    {
        $hint = CompanyEmailDomain::hint();

        $this->assertStringContainsString('approved company work email', $hint);
        $this->assertStringContainsString('@tanseeqllc.com', $hint);
        $this->assertStringContainsString('@waterinmotionllc.com', $hint);
        $this->assertStringContainsString('@proscapeuae.com', $hint);
    }
}
