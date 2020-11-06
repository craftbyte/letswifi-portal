<?php declare(strict_types=1);

/*
 * This file is part of letswifi; a system for easy eduroam device enrollment
 *
 * Copyright: 2018-2020, Jørn Åne de Jong, Uninett AS <jorn.dejong@uninett.no>
 * SPDX-License-Identifier: BSD-3-Clause
 */

namespace letswifi\profile\generator;

use fyrkat\openssl\PKCS12;
use InvalidArgumentException;

use letswifi\profile\auth\AbstractAuth;
use letswifi\profile\auth\TlsAuth;

use letswifi\profile\network\HS20Network;
use letswifi\profile\network\SSIDNetwork;

/**
 * @suppress PhanParamNameIndicatingUnusedInClosure
 */
class MobileConfigGenerator extends AbstractGenerator
{
	/**
	 * Generate the eap-config profile
	 */
	public function generate(): string
	{
		$uuid = static::uuidgen();
		$identifier = \implode( '.', \array_reverse( \explode( '.', $this->profileData->getRealm() ) ) );
		/** @var array<TlsAuth> */
		$caCertificates = [];
		$tlsAuthMethods = \array_filter(
				$this->authenticationMethods,
				static function( $a ) { return $a instanceof TlsAuth && null !== $a->getPKCS12(); }
			);
		if ( 1 !== \count( $tlsAuthMethods ) ) {
			throw new InvalidArgumentException( 'Expected 1 TLS auth method, got ' . \count( $tlsAuthMethods ) );
		}
		$tlsAuthMethod = \reset( $tlsAuthMethods );
		\assert( $tlsAuthMethod instanceof TlsAuth );
		$tlsAuthMethodUuid = static::uuidgen();
		$passphrase = $tlsAuthMethod->getPassphrase();
		if ( $pkcs12 = $tlsAuthMethod->getPKCS12() ) {
			// Remove the CA from the PKCS12 object,
			// because otherwise MacOS would trust that CA for HTTPS traffic
			$pkcs12 = new PKCS12( $pkcs12->getX509(), $pkcs12->getPrivateKey() );
		}
		/** @var array<\fyrkat\openssl\X509> */
		$caCertificates = \array_merge( $caCertificates, $tlsAuthMethod->getServerCACertificates() );
		\assert( null !== $pkcs12 );

		$result = '<?xml version="1.0" encoding="UTF-8"?>'
			. "\n" . '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">'
			. "\n" . '<plist version="1.0">'
			. "\n" . '<dict>'
			. "\n" . '	<key>PayloadDisplayName</key>'
			. "\n" . '	<string>' . static::e( $this->profileData->getDisplayName() ) . '</string>'
			. "\n" . '	<key>PayloadIdentifier</key>'
			. "\n" . '	<string>' . static::e( $identifier ) . '</string>'
			. "\n" . '	<key>PayloadRemovalDisallowed</key>'
			. "\n" . '	<false/>'
			. "\n" . '	<key>PayloadType</key>'
			. "\n" . '	<string>Configuration</string>'
			. "\n" . '	<key>PayloadUUID</key>'
			. "\n" . '	<string>' . static::e( $uuid ) . '</string>'
			. "\n" . '	<key>PayloadVersion</key>'
			. "\n" . '	<integer>1</integer>'
			. "\n";
		if ( null !== $description = $this->profileData->getDescription() ) {
			$result .= '	<key>PayloadDescription</key>'
				. "\n" . '	<string>' . static::e( $description ) . '</string>'
				. "\n";
		}
		$result .= '	<key>PayloadContent</key>'
			. "\n" . '	<array>'
			. "\n" . '		<dict>'
			. "\n" . '			<key>Password</key>'
			. "\n" . '			<string>' . static::e( $passphrase ) . '</string>'
			. "\n" . '			<key>PayloadUUID</key>'
			. "\n" . '			<string>' . static::e( $tlsAuthMethodUuid ) . '</string>'
			. "\n" . '			<key>PayloadCertificateFileName</key>'
			. "\n" . '			<string>' . static::e( $pkcs12->getX509()->getSubject()->getCommonName() ) . '.p12</string>'
			. "\n" . '			<key>PayloadDisplayName</key>'
			. "\n" . '			<string>' . static::e( $pkcs12->getX509()->getSubject()->getCommonName() ) . '</string>'
			. "\n" . '			<key>PayloadContent</key>'
			. "\n" . '			<data>'
			. "\n" . '				' . static::e( static::columnFormat( \base64_encode( $pkcs12->getPKCS12Bytes( $passphrase ) ), 52, 4 ) )
			. "\n" . '			</data>'
			. "\n" . '			<key>PayloadType</key>'
			. "\n" . '			<string>com.apple.security.pkcs12</string>'
			. "\n" . '			<key>PayloadVersion</key>'
			. "\n" . '			<integer>1</integer>'
			. "\n" . '		</dict>'
			. "\n";

		$uuids = \array_map(
				static function( $_ ){ return static::uuidgen(); },
				\array_fill( 0, \count( $caCertificates ), null )
			);
		/** @var array<string,\fyrkat\openssl\X509> */
		$caCertificates = \array_combine( $uuids, $caCertificates );
		foreach ( $caCertificates as $uuid => $ca ) {
			$result .= ''
				. "\n" . '		<dict>'
				. "\n" . '			<key>PayloadCertificateFileName</key>'
				. "\n" . '			<string>' . static::e( $ca->getSubject()->getCommonName() ) . '.cer</string>'
				. "\n" . '			<key>PayloadContent</key>'
				. "\n" . '			<data>'
				. "\n" . '				' . static::e( static::columnFormat( AbstractAuth::pemToBase64Der( $ca->getX509Pem() ), 52, 4 ) )
				. "\n" . '			</data>'
				. "\n" . '			<key>PayloadDisplayName</key>'
				. "\n" . '			<string>' . static::e( $ca->getSubject()->getCommonName() ) . '</string>'
				. "\n" . '			<key>PayloadIdentifier</key>'
				. "\n" . '			<string>' . static::e( $identifier ) . '.' . static::e( $uuid ) . '</string>'
				. "\n" . '			<key>PayloadType</key>'
				. "\n" . '			<string>com.apple.security.root</string>'
				. "\n" . '			<key>PayloadUUID</key>'
				. "\n" . '			<string>' . static::e( $uuid ) . '</string>'
				. "\n" . '			<key>PayloadVersion</key>'
				. "\n" . '			<integer>1</integer>'
				. "\n" . '		</dict>'
				. "\n";
		}
		foreach ( $this->profileData->getNetworks() as $network ) {
			if ( $network instanceof SSIDNetwork ) {
				// TODO assumes TLSAuth, it's the only option currently
				$result .= '		<dict>'
					. "\n" . '			<key>AutoJoin</key>'
					. "\n" . '			<true/>'
					. "\n" . '			<key>EAPClientConfiguration</key>'
					. "\n" . '			<dict>'
					. "\n" . '				<key>AcceptEAPTypes</key>'
					. "\n" . '				<array>'
					. "\n" . '					<integer>13</integer>'
					. "\n" . '				</array>'
					. "\n" . '				<key>EAPFASTProvisionPAC</key>'
					. "\n" . '				<false/>'
					. "\n" . '				<key>EAPFASTProvisionPACAnonymously</key>'
					. "\n" . '				<false/>'
					. "\n" . '				<key>EAPFASTUsePAC</key>'
					. "\n" . '				<false/>'
					. "\n" . '				<key>PayloadCertificateAnchorUUID</key>'
					. "\n" . '				<array>'
					. "\n";
				foreach ( $caCertificates as $uuid => $_ ) {
					$result .= '					<string>' . static::e( $uuid ) . '</string>'
						. "\n";
				}
				$result .= '				</array>'
					. "\n" . '				<key>TLSTrustedServerNames</key>'
					. "\n" . '				<array>'
					. "\n";
				foreach ( $tlsAuthMethod->getServerNames() as $serverName ) {
					$result .= '					<string>' . static::e( $serverName ) . '</string>'
						. "\n";
				}
				$result .= '				</array>'
					. "\n" . '			</dict>'
					. "\n" . '			<key>EncryptionType</key>'
					. "\n" . '			<string>WPA</string>'
					. "\n" . '			<key>HIDDEN_NETWORK</key>'
					. "\n" . '			<false/>'
					. "\n" . '			<key>PayloadCertificateUUID</key>'
					. "\n" . '			<string>' . static::e( $tlsAuthMethodUuid ) . '</string>'
					. "\n" . '			<key>PayloadDisplayName</key>'
					. "\n" . '			<string>Wi-Fi (' . static::e( $network->getSSID() ) . ')</string>'
					. "\n" . '			<key>PayloadIdentifier</key>'
					. "\n" . '			<string>' . static::e( $identifier ) . '.wifi</string>'
					. "\n" . '			<key>PayloadType</key>'
					. "\n" . '			<string>com.apple.wifi.managed</string>'
					. "\n" . '			<key>PayloadUUID</key>'
					. "\n" . '			<string>' . static::uuidgen() . '</string>'
					. "\n" . '			<key>PayloadVersion</key>'
					. "\n" . '			<integer>1</integer>'
					. "\n" . '			<key>ProxyType</key>'
					. "\n" . '			<string>None</string>'
					. "\n" . '			<key>SSID_STR</key>'
					. "\n" . '			<string>' . static::e( $network->getSSID() ) . '</string>'
					. "\n" . '		</dict>'
					. "\n";
			} elseif ( $network instanceof HS20Network ) {
			} else {
				throw new InvalidArgumentException( 'Only SSID or Hotspot 2.0 networks are supported, got ' . \get_class( $network ) );
			}
		}
		$result .= '	</array>'
			. "\n" . '</dict>'
			. "\n" . '</plist>'
			. "\n";

		return $result;
	}
	public function getFileExtension():string{
		return 'mobileconfig';
	}

	public function getContentType(): string
	{
		return 'application/x-apple-aspen-config';
	}
}