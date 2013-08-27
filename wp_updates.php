<?php

/*
 * BEGIN CONFIGURATION
 **********************************************************************************************************************/

define('DB_HOST',						'');
define('DB_USER',						'');
define('DB_PASSWORD',				'');
define('DB_SCHEMA',					'');

define('EMAIL_SERVER',			'');
define('EMAIL_PORT',				'');
define('EMAIL_USER',				'');
define('EMAIL_PASSWORD',		'');
define('EMAIL_FROM_NAME',		'');
define('EMAIL_FROM_EMAIL',	'');
define('EMAIL_REPLAY_TO',		'');

/*
 * END CONFIGURATION
 **********************************************************************************************************************/


mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
mysql_query('SET character_set_results = utf8');
mysql_select_db(DB_SCHEMA);

$q_sites = mysql_query('SELECT * FROM sites');

/** @todo Gestión de errores: si no existen las tablas necesarias */
/** @todo Gestión de errores: si no existen registros en la tabla sites */

while ($site = mysql_fetch_assoc($q_sites))
{
	echo "Checking for updates on {$site['database']}... ";

	mysql_select_db($site['database']);

	$rs = mysql_query("SELECT option_name, option_value FROM wp_options WHERE option_name LIKE '_site_transient_update_%'");

	$processed_update_info = array();

	/*
	 * Parsear los objetos serializados en la BD de WP.
	 */

	while ($fila = mysql_fetch_assoc($rs))
	{
		$update_info	= unserialize($fila['option_value']);
		$last_checked	= $update_info->last_checked;

		switch ($fila['option_name'])
		{
			case '_site_transient_update_core':
				if (isset($update_info->updates[0]->response) && $update_info->updates[0]->response == 'upgrade')
				{
					$processed_update_info['core'] = $update_info->updates[0]->current;
				}
				break;

			case '_site_transient_update_plugins':
				if (empty($update_info->response))
				{
					break;
				}
				foreach ($update_info->response as $update_details)
				{
					if (!isset($processed_update_info['plugins']))
					{
						$processed_update_info['plugins'] = array();
					}
					$processed_update_info['plugins'][] = $update_details->slug;
				}
				break;

			case '_site_transient_update_themes':
				if (empty($update_info->response))
				{
					break;
				}
				foreach ($update_info->response as $theme=>$update_details)
				{
					if (!isset($processed_update_info['themes']))
					{
						$processed_update_info['themes'] = array();
					}
					$processed_update_info['themes'][] = $theme;
				}
				break;
		}
	}

	/*
	 * Guardar los datos parseados de updates en la BD de Raices
	 */

	mysql_select_db(DB_SCHEMA);

	$sql_insert = <<<SQL
INSERT INTO
	checks (site, time, result)
VALUES
	('%s', NOW(), '%s')
SQL;

	mysql_query(sprintf($sql_insert,
		mysql_real_escape_string($site['database']),
		mysql_real_escape_string(join(',', array_keys($processed_update_info)))
	));

	if (empty($processed_update_info))
	{
		echo "up-to-date\n";
		continue;
	}
	else
	{
		echo 'found updates on ' . join(', ', array_keys($processed_update_info)) . "\n";
	}

	/*
	 * Obtener el número de días que el WP lleva desactualizado (primer KO después del último OK)
	 */

	$sql_days = <<<SQL
SELECT DATEDIFF(now(), (
	SELECT time FROM checks WHERE NOT result = '' AND site='{$site['database']}' AND time > (
		SELECT time FROM checks WHERE result = '' AND site='{$site['database']}' ORDER BY time DESC LIMIT 1
	) LIMIT 1
)) AS outdated_days
SQL;

	$rs_days = mysql_query($sql_days);
	$days = mysql_fetch_row($rs_days);
	$days = $days[0];

	/*
	 * Componer el e-mail de notificación
	 */

	$email_subject = empty($days) 
		? 'El Wordpress de ' . $site['domain'] . ' está desactualizado'
		: 'El Wordpress de ' . $site['domain'] . " lleva $days días desactualizado";

	$email_updates_list = '';

	if (isset($processed_update_info['core']))
	{
		$email_updates_list .= '- Actualización del núcleo: versión ' . $processed_update_info['core'] . "\n";
	}

	if (!empty($processed_update_info['plugins']))
	{
		$email_updates_list .= '- ' . count($processed_update_info['plugins']) . ' actualizacion(es) de plug-ins: ' . join(', ', $processed_update_info['plugins']) . "\n";
	}

	if (!empty($processed_update_info['themes']))
	{
		$email_updates_list .= '- ' . count($processed_update_info['themes']) . ' actualizacion(es) de temas: ' . join(', ', $processed_update_info['themes']) . "\n";
	}

	/** @todo Usar sprintf para meter las variables aquí. Así podemos sacar la plantilla del mensaje a un config */
	$email_body = <<<TEXT
Hola {$site['owner_name']},

el Wordpress de {$site['domain']} tiene las siguientes actualizaciones disponibles:

$email_updates_list
ES IMPORTANTE que actualices el Wordpress cuanto antes, de lo contrario estás exponiendo tu web a ataques de seguridad, 
incompatibilidades y comportamientos inesperados.

Por favor, haz click aquí para actualizar tu Wordpress: {$site['url']}wp-admin/update-core.php

Gracias,

Israel Viana
TEXT;

	/*
	 * Enviar el e-mail
	 */

	include_once __DIR__ . '/vendor/autoload.php';

	$transport = Swift_SmtpTransport::newInstance(EMAIL_SERVER, EMAIL_PORT)
		->setUsername(EMAIL_USER)
		->setPassword(EMAIL_PASSWORD);

	$mailer = Swift_Mailer::newInstance($transport);

	$message = Swift_Message::newInstance($email_subject)
		->setFrom(array(EMAIL_FROM_EMAIL => EMAIL_FROM_NAME))
		->setReplyTo(array(EMAIL_REPLAY_TO))
		->setTo(array($site['owner_email']))
		->setBody($email_body);

	$result = $mailer->send($message);
}
