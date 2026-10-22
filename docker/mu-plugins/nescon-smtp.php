<?php
/**
 * Plugin Name: Nescon SMTP
 * Description: Envia os e-mails do WordPress (recuperação de senha, Contact Form 7) por SMTP, configurado pelas variáveis de ambiente SMTP_*.
 *
 * Instalado automaticamente pelo docker/provision.sh. Sem SMTP_HOST
 * definido, não faz nada e o WordPress usa o envio padrão do PHP.
 */

if ( ! getenv( 'SMTP_HOST' ) ) {
	return;
}

add_action(
	'phpmailer_init',
	function ( $phpmailer ) {
		$phpmailer->isSMTP();
		$phpmailer->Host = getenv( 'SMTP_HOST' );
		$phpmailer->Port = (int) ( getenv( 'SMTP_PORT' ) ?: 587 );

		// "tls" (STARTTLS, porta 587), "ssl" (porta 465) ou "none".
		$secure = strtolower( getenv( 'SMTP_SECURE' ) ?: 'tls' );
		$phpmailer->SMTPSecure = 'none' === $secure ? '' : $secure;
		$phpmailer->SMTPAutoTLS = 'none' !== $secure;

		if ( getenv( 'SMTP_USER' ) ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = getenv( 'SMTP_USER' );
			$phpmailer->Password = getenv( 'SMTP_PASSWORD' );
		}

		$from = getenv( 'SMTP_FROM' ) ?: getenv( 'SMTP_USER' );
		if ( $from ) {
			$phpmailer->setFrom( $from, getenv( 'SMTP_FROM_NAME' ) ?: get_bloginfo( 'name' ), false );
		}
	}
);
