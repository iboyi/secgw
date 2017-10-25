<?php
/*
 * interfaces_gre_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-interfaces-gre-edit
##|*NAME=Interfaces: GRE: Edit
##|*DESCR=Allow access to the 'Interfaces: GRE: Edit' page.
##|*MATCH=interfaces_gre_edit.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("functions.inc");

if (!is_array($config['gres']['gre'])) {
	$config['gres']['gre'] = array();
}

$a_gres = &$config['gres']['gre'];
$id = $_REQUEST['id'];

if (isset($id) && $a_gres[$id]) {
	$pconfig['if'] = $a_gres[$id]['if'];
	$pconfig['greif'] = $a_gres[$id]['greif'];
	$pconfig['remote-addr'] = $a_gres[$id]['remote-addr'];
	$pconfig['tunnel-remote-net'] = $a_gres[$id]['tunnel-remote-net'];
	$pconfig['tunnel-local-addr'] = $a_gres[$id]['tunnel-local-addr'];
	$pconfig['tunnel-remote-addr'] = $a_gres[$id]['tunnel-remote-addr'];
	$pconfig['link1'] = isset($a_gres[$id]['link1']);
	$pconfig['link2'] = isset($a_gres[$id]['link2']);
	$pconfig['link0'] = isset($a_gres[$id]['link0']);
	$pconfig['descr'] = $a_gres[$id]['descr'];
}

if ($_POST['save']) {

	unset($input_errors);
	$pconfig = $_POST;

	$pconfig['tunnel-local-addr'] = addrtolower($_POST['tunnel-local-addr']);
	$pconfig['tunnel-remote-addr'] = addrtolower($_POST['tunnel-remote-addr']);
	$pconfig['remote-addr'] = addrtolower($_POST['remote-addr']);

	/* input validation */
	$reqdfields = explode(" ", "if remote-addr tunnel-local-addr tunnel-remote-addr tunnel-remote-net");
	$reqdfieldsn = array(gettext("Parent interface"), gettext("Remote tunnel endpoint IP address"), gettext("Local tunnel IP address"), gettext("Remote tunnel IP address"), gettext("Remote tunnel network"));

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if ((!is_ipaddr($_POST['tunnel-local-addr'])) ||
	    (!is_ipaddr($_POST['tunnel-remote-addr'])) ||
	    (!is_ipaddr($_POST['remote-addr']))) {
		$input_errors[] = gettext("The tunnel local and tunnel remote fields must have valid IP addresses.");
	}

	if (!is_numericint($_POST['tunnel-remote-net'])) {
		$input_errors[] = gettext("The GRE tunnel subnet must be an integer.");
	}

	if (is_ipaddrv4($_POST['tunnel-local-addr'])) {
		if (!is_ipaddrv4($_POST['tunnel-remote-addr'])) {
			$input_errors[] = gettext("The GRE Tunnel remote address must be IPv4 where tunnel local address is IPv4.");
		}
		if ($_POST['tunnel-remote-net'] > 32 || $_POST['tunnel-remote-net'] < 1) {
			$input_errors[] = gettext("The GRE tunnel subnet must be an integer between 1 and 32.");
		}
	}

	if (is_ipaddrv6($_POST['tunnel-local-addr'])) {
		if (!is_ipaddrv6($_POST['tunnel-remote-addr'])) {
			$input_errors[] = gettext("The GRE Tunnel remote address must be IPv6 where tunnel local address is IPv6.");
		}
		if ($_POST['tunnel-remote-net'] > 128 || $_POST['tunnel-remote-net'] < 1) {
			$input_errors[] = gettext("The GRE tunnel subnet must be an integer between 1 and 128.");
		}
	}

	foreach ($a_gres as $gre) {
		if (isset($id) && ($a_gres[$id]) && ($a_gres[$id] === $gre)) {
			continue;
		}

		if (($gre['if'] == $_POST['if']) && ($gre['tunnel-remote-addr'] == $_POST['tunnel-remote-addr'])) {
			$input_errors[] = sprintf(gettext("A GRE tunnel with the network %s is already defined."), $gre['remote-network']);
			break;
		}
	}

	if (!$input_errors) {
		$gre = array();
		$gre['if'] = $_POST['if'];
		$gre['tunnel-local-addr'] = $_POST['tunnel-local-addr'];
		$gre['tunnel-remote-addr'] = $_POST['tunnel-remote-addr'];
		$gre['tunnel-remote-net'] = $_POST['tunnel-remote-net'];
		$gre['remote-addr'] = $_POST['remote-addr'];
		$gre['descr'] = $_POST['descr'];
		if (isset($_POST['link1'])) {
			$gre['link1'] = '';
		}
		$gre['greif'] = $_POST['greif'];

		$gre['greif'] = interface_gre_configure($gre);
		if ($gre['greif'] == "" || !stristr($gre['greif'], "gre")) {
			$input_errors[] = gettext("Error occurred creating interface, please retry.");
		} else {
			if (isset($id) && $a_gres[$id]) {
				$a_gres[$id] = $gre;
			} else {
				$a_gres[] = $gre;
			}

			write_config();

			$confif = convert_real_interface_to_friendly_interface_name($gre['greif']);

			if ($confif != "") {
				interface_configure($confif);
			}

			header("Location: interfaces_gre.php");
			exit;
		}
	}
}

function build_parent_list() {
	$parentlist = array();
	$portlist = get_possible_listen_ips();
	foreach ($portlist as $ifn => $ifinfo) {
		$parentlist[$ifn] = $ifinfo;
	}

	return($parentlist);
}

$pgtitle = array(gettext("Interfaces"), gettext("GREs"), gettext("Edit"));
$pglinks = array("", "interfaces_gre.php", "@self");
$shortcut_section = "interfaces";
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form();

$section = new Form_Section('GRE Configuration');

$section->addInput(new Form_Select(
	'if',
	'*Parent Interface',
	$pconfig['if'],
	build_parent_list()
))->setHelp('This interface serves as the local address to be used for the GRE tunnel.');

$section->addInput(new Form_IpAddress(
	'remote-addr',
	'*GRE Remote Address',
	$pconfig['remote-addr']
))->setHelp('Peer address where encapsulated GRE packets will be sent.');

$section->addInput(new Form_IpAddress(
	'tunnel-local-addr',
	'*GRE tunnel local address',
	$pconfig['tunnel-local-addr']
))->setHelp('Local GRE tunnel endpoint.');

$section->addInput(new Form_IpAddress(
	'tunnel-remote-addr',
	'*GRE tunnel remote address',
	$pconfig['tunnel-remote-addr']
))->setHelp('Remote GRE address endpoint.');

$section->addInput(new Form_Select(
	'tunnel-remote-net',
	'*GRE tunnel subnet',
	$pconfig['tunnel-remote-net'],
	array_combine(range(128, 1, -1), range(128, 1, -1))
))->setHelp('The subnet is used for determining the network that is tunnelled.');

$section->addInput(new Form_Checkbox(
	'link1',
	'Add Static Route',
	'Add an explicit static route for the remote inner tunnel address/subnet via the local tunnel address',
	$pconfig['link1']
));

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('A description may be entered here for administrative reference (not parsed).');

$section->addInput(new Form_Input(
	'greif',
	null,
	'hidden',
	$pconfig['greif']
));

if (isset($id) && $a_gres[$id]) {
	$section->addInput(new Form_Input(
		'id',
		null,
		'hidden',
		$id
	));
}

$form->add($section);
print($form);

include("foot.inc");
