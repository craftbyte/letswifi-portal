<?php declare(strict_types=1);

/*
 * This file is part of letswifi; a system for easy eduroam device enrollment
 *
 * Copyright: 2018-2022, Jørn Åne de Jong <jorn.dejong@letswifi.eu>
 * Copyright: 2020-2022, Paul Dekkers, SURF <paul.dekkers@surf.nl>
 * SPDX-License-Identifier: BSD-3-Clause
 */

require \implode(\DIRECTORY_SEPARATOR, [\dirname(__DIR__, 2), 'src', '_autoload.php']);
$basePath = '..';

$app = new letswifi\LetsWifiApp();
$app->registerExceptionHandler();

$app->render( [
	'href' => $basePath . '/app/',
	'apps' => [
		'android' => [
			'url' => 'https://play.google.com/store/apps/details?id=app.eduroam.geteduroam',
			'name' => 'Android',
		],
		'ios' => [
			'url' => 'https://apps.apple.com/app/geteduroam/id1504076137',
			'name' => 'iOS',
		],
		'windows' => [
			'url' => 'https://geteduroam.app/app/geteduroam.exe',
			'name' => 'Windows',
		],
	],
	'mobileconfig' => [
		'url' => "${basePath}/profiles/mac/",
	],
	'manual' => [
		'url' => "${basePath}profiles/new/",
	],
], 'app', $basePath );
