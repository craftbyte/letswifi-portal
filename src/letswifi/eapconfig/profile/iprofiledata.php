<?php declare(strict_types=1);

/*
 * This file is part of letswifi; a system for easy eduroam device enrollment
 *
 * Copyright: 2018-2020, Jørn Åne de Jong, Uninett AS <jorn.dejong@uninett.no>
 * SPDX-License-Identifier: BSD-3-Clause
 */

namespace letswifi\EapConfig\Profile;

use letswifi\EapConfig\CredentialApplicability\ICredentialApplicability;

interface IProfileData
{
	public function getRealm(): string;

	public function getLanguageCode(): string;

	/**
	 * @return array<ICredentialApplicability>
	 */
	public function getCredentialApplicabilities(): array;

	public function getDisplayName(): string;

	public function getDescription(): ?string;

	public function getProviderLocation(): ?Location;

	public function getProviderLogo(): ?Logo;

	public function getTermsOfUse(): ?string;

	public function getHelpDesk(): ?Helpdesk;
}