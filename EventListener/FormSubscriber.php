<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\InfoBipSmsBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\InfoBipSmsBundle\Api\AbstractSmsApi;
use Mautic\SmsBundle\Event\SmsSendEvent;
use MauticPlugin\InfoBipSmsBundle\Helper\SmsHelper;
use MauticPlugin\InfoBipSmsBundle\Model\SmsModel;
use Mautic\SmsBundle\SmsEvents;
use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Entity\Action;

/**
 * Class FormSubscriber.
 */
class FormSubscriber extends CommonSubscriber
{
	
	public function __construct(
			CoreParametersHelper $coreParametersHelper,
			LeadModel $leadModel,
			SmsModel $smsModel,
			AbstractSmsApi $smsApi,
			SmsHelper $smsHelper
			) {
				$this->coreParametersHelper = $coreParametersHelper;
				$this->leadModel            = $leadModel;
				$this->smsModel             = $smsModel;
				$this->smsApi               = $smsApi;
				$this->smsHelper            = $smsHelper;
	}
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::FORM_ON_BUILD => ['onFormBuilder', 0],
        	FormEvents::FORM_ON_SUBMIT => ['onFormSubmit', 0]
        ];
    }

    /**
     * Add a send email actions to available form submit actions.
     *
     * @param FormBuilderEvent $event
     */
    public function onFormBuilder(FormBuilderEvent $event)
    {
        // Send email to lead
        $action = [
            'group'           => 'mautic.sms.actions',
            'label'           => 'mautic.campaign.infobip_sms.send_text_sms',
            'description'     => 'mautic.campaign.sms.send_text_sms.tooltip',
            'formType'        => 'sms_list',
            'formTheme'       => 'MauticEmailBundle:FormTheme\EmailSendList',
            'callback'        => '',
        ];

        $event->addSubmitAction('sms.send.lead', $action);
    }
    
    public function onFormSubmit(SubmissionEvent $event) 
    {
    	$type = $event->getForm()->getActions()->first()->getType();
    	
    	if($type == 'sms.send.lead') {
	    	$data =  $event->getPost();
	    	$properties = $event->getForm()->getActions()->first()->getProperties();
	    	$currentLead = $event->getLead();
	    	$smsModel = $this->smsModel;
	    	
	    	foreach($properties as $k => $smsId) {
	    		try {
		    		$sms   = $smsModel->getEntity($smsId);
		    		
		    		$smsEvent = new SmsSendEvent($sms->getMessage(), $currentLead);
		    		$smsEvent->setSmsId($smsId);
		    		
		    		$this->dispatcher->dispatch(SmsEvents::SMS_ON_SEND, $smsEvent);
		    		
		    		$tokenEvent = $this->dispatcher->dispatch(
		    				SmsEvents::TOKEN_REPLACEMENT,
		    				new TokenReplacementEvent(
		    						$smsEvent->getContent(),
		    						$currentLead,
		    						['channel' => ['sms', $sms->getId()]]
		    					)
		    				);
		    		
		    		$response = $this->smsApi->sendSms($data['phone'], $tokenEvent->getContent());
	    		} catch(Exception $e) {
	    			return $e->getMessage();
	    		}
	    	}
    	}
 		return true;
    }
}
