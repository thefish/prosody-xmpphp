<?php
/**
 * XMPPHP: The PHP XMPP Library
 * Copyright (C) 2008  Nathanael C. Fritz
 * This file is part of SleekXMPP.
 * 
 * XMPPHP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * XMPPHP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with XMPPHP; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category   xmpphp 
 * @package	XMPPHP
 * @author	 Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author	 Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author	 Michael Garvin <JID: gar@netflint.net>
 * @copyright  2008 Nathanael C. Fritz
 */

/**
 * XMPPHP Roster Object
 * 
 * @category   xmpphp 
 * @package	XMPPHP
 * @author	 Nathanael C. Fritz <JID: fritzy@netflint.net>
 * @author	 Stephan Wentz <JID: stephan@jabber.wentz.it>
 * @author	 Michael Garvin <JID: gar@netflint.net>
 * @copyright  2008 Nathanael C. Fritz
 * @version	$Id$
 */

class Roster {
	/**
	 * Roster array, handles contacts and presence.  Indexed by jid.
	 * Contains array with potentially two indexes 'contact' and 'presence'
	 * @var array
	 */
	protected $rosterArray = array();
	/**
	 * Constructor
	 * 
	 */
	public function __construct($rosterArray = array()) {
		if ($this->verifyRoster($rosterArray)) {
			$this->rosterArray = $rosterArray; //Allow for prepopulation with existing roster
		} else {
			$this->rosterArray = array();
		}
	}

	/**
	 *
	 * Check that a given roster array is of a valid structure (empty is still valid)
	 *
	 * @param array $rosterArray
	 */
	protected function verifyRoster($rosterArray) {
        $rosterArray = null; // cause not used so far
		#TODO once we know *what* a valid roster array looks like
		return True;
	}

	/**
	 *
	 * Add given contact to roster
	 *
	 * @param string $jid
	 * @param string $subscription
	 * @param string $name
	 * @param array $groups
	 */
	public function addContact($jid, $subscription, $name='', $groups=array()) {
		$contact = array('jid' => $jid, 'subscription' => $subscription, 'name' => $name, 'groups' => $groups);
		if ($this->isContact($jid)) {
			$this->rosterArray[$jid]['contact'] = $contact;
		} else {
			$this->rosterArray[$jid] = array('contact' => $contact);
		}
	}

	/**
	 * 
	 * Retrieve contact via jid
	 *
	 * @param string $jid
	 */
	public function getContact($jid) {
		if ($this->isContact($jid)) {
			return $this->rosterArray[$jid]['contact'];
		}
	}

	/**
	 *
	 * Discover if a contact exists in the roster via jid
	 *
	 * @param string $jid
	 */
	public function isContact($jid) {
		return (array_key_exists($jid, $this->rosterArray));
	}

	/**
	 *
	 * Set presence
	 *
	 * @param string $presence
	 * @param integer $priority
	 * @param string $show
	 * @param string $status
	*/
	public function setPresence($presence, $priority, $show, $status) {
        // PHP 5.3+ Fix
		list($jid, $resource) = preg_split("/\//", $presence);
		if ($show != 'unavailable') {
			if (!$this->isContact($jid)) {
				$this->addContact($jid, 'not-in-roster');
			}
			$resource = $resource ? $resource : '';
			$this->rosterArray[$jid]['presence'][$resource] = array('priority' => $priority, 'show' => $show, 'status' => $status);
		} else { //Nuke unavailable resources to save memory
			unset($this->rosterArray[$jid]['resource'][$resource]);
		}
	}

	/*
	 *
	 * Return best presence for jid
	 *
	 * @param string $jid
	 */
	public function getPresence($jid) {
        // PHP 5.3+ Fix
		$split = preg_split("/\//", $jid);
		$jid = $split[0];
		if($this->isContact($jid)) {
			$current = array('resource' => '', 'active' => '', 'priority' => -129, 'show' => '', 'status' => ''); //Priorities can only be -128 = 127
			foreach($this->rosterArray[$jid]['presence'] as $resource => $presence) {
				//Highest available priority or just highest priority
				if ($presence['priority'] > $current['priority'] and (($presence['show'] == "chat" or $presence['show'] == "available") or ($current['show'] != "chat" or $current['show'] != "available"))) {
					$current = $presence;
					$current['resource'] = $resource;
				}
			}
			return $current;
		}
	}
	/**
	 *
	 * Get roster
	 *
	 */
	public function getRoster() {
		return $this->rosterArray;
	}
}
?>
