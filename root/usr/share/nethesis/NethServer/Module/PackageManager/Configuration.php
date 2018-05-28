<?php

/*
 * Copyright (C) 2018 Nethesis S.r.l.
 * http://www.nethesis.it - nethserver@nethesis.it
 *
 * This script is part of NethServer.
 *
 * NethServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License,
 * or any later version.
 *
 * NethServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NethServer.  If not, see COPYING.
 */

namespace NethServer\Module\PackageManager;
use Nethgui\System\PlatformInterface as Validate;

class Configuration extends \Nethgui\Controller\Collection\AbstractAction implements \Nethgui\Component\DependencyConsumer
{
    public function initialize()
    {
        $this->declareParameter('NsReleaseLock', $this->createValidator()->notEmpty()->memberOf('enabled', 'disabled'), array('configuration', 'sysconfig', 'NsReleaseLock'));
        $this->declareParameter('messages', Validate::YES_NO, array('configuration', 'yum-cron', 'messages'));
        $this->declareParameter('download', Validate::YES_NO, array('configuration', 'yum-cron', 'download'));
        $this->declareParameter('applyUpdate', Validate::YES_NO, array('configuration', 'yum-cron', 'applyUpdate'));
        $this->declareParameter('customMail', Validate::ANYTHING, array('configuration', 'yum-cron', 'customMail'));
        $this->declareParameter('status', Validate::SERVICESTATUS, array('configuration', 'yum-cron', 'status'));
    }

    public function bind(\Nethgui\Controller\RequestInterface $request)
    {
        parent::bind($request);
        if($request->isMutation() && $request->hasParameter('customMail')) {
            $this->parameters['customMail'] = implode(",", self::splitLines($request->getParameter('customMail')));
        }
    }

    public function validate(\Nethgui\Controller\ValidationReportInterface $report)
    {
        parent::validate($report);
        $forwards = $this->parameters['customMail'];
        if($forwards) {
            $emailValidator = $this->createValidator(Validate::EMAIL);
            foreach(explode(',', $forwards) as $email) {
                if( !$emailValidator->evaluate($email)) {
                    $report->addValidationErrorMessage($this, 'customMail',
                        'valid_mailforward_address', array($email));
                }
            }
        }
   }

    public static function splitLines($text)
    {
        return array_filter(preg_split("/[,;\s]+/", $text));
    }

    public function onParametersSaved($changes)
    {
        $this->getPlatform()->signalEvent('software-repos-save &');
    }

    public function prepareView(\Nethgui\View\ViewInterface $view)
    {
        parent::prepareView($view);
        $db = $this->getPlatform()->getDatabase('configuration');
        $view['Version'] = $db->getProp('sysconfig', 'Version');
        $view['PolicyDisabled'] = (bool) $db->getProp('subscription', 'SystemId') || @file_exists('/etc/e-smith/db/configuration/force/sysconfig/NsReleaseLock');
        $view['BackToModules'] = $view->getModuleUrl('../Modules');
        if(isset($this->parameters['customMail'])) {
            $view['customMail'] = implode("\r\n", explode(',', $this->parameters['customMail']));
        }
        if($this->getRequest()->isValidated()) {
            $view->getCommandList()->show();
            $db = $this->getPlatform()->getDatabase('configuration');
            $nsReleaseLock = $db->getProp('sysconfig', 'NsReleaseLock');
            if( ! $nsReleaseLock) {
                $this->notifications->warning($view->translate('NsReleaseLock_policy_warning'));
            }
        }
        if ($this->getRequest()->isMutation()) {
            $this->getPlatform()->setDetachedProcessCondition('success', array(
                'location' => array(
                    'url' => $view->getModuleUrl('../Modules'),
                    'freeze' => TRUE,
            )));
        }
    }

    public function setUserNotifications(\Nethgui\Model\UserNotifications $n)
    {
        $this->notifications = $n;
        return $this;
    }

    public function getDependencySetters()
    {
        return array('UserNotifications' => array($this, 'setUserNotifications'));
    }
}