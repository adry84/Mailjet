<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Mailjet\EventListeners;

use Mailjet\Mailjet;
use Mailjet\Model\MailjetContactsList;
use Mailjet\Model\MailjetContactsListQuery;
use Mailjet\Model\MailjetNewsletter;
use Mailjet\Model\MailjetNewsletterQuery;
use Mailjet\templates\MailjetHelper;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Acl\Exception\Exception;
use Symfony\Component\Translation\TranslatorInterface;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\NewsletterQuery;

/**
 * Class NewsletterListener
 * @package Mailjet\EventListeners
 * @author Benjamin Perche <bperche@openstudio.com>
 */
class NewsletterListener implements EventSubscriberInterface
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;


    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param NewsletterEvent $event
     * @throws PropelException
     */
    public function subscribe(NewsletterEvent $event)
    {
        $model = $this->apiAddUser($event);
        // Create contact
        if (null !== $model) {
            // Add contact to the thelia list
            $this->apiAddContactList($event, $model);
        }
    }

    /**
     * @param NewsletterEvent $event
     * @throws PropelException
     */
    public function update(NewsletterEvent $event)
    {
        $previousEmail = NewsletterQuery::create()->findPk($event->getId())->getEmail();

        if ($event->getEmail() === $previousEmail) {
            return;
        }
        /** @noinspection PhpParamsInspection */
        if (null !== $model = MailjetNewsletterQuery::create()->findOneByEmail($previousEmail)) {
            $relationId = $model->getRelationId();
            $removed = MailjetHelper::getInstance()->removeContactFromList($event->getEmail(), $relationId);
            if ($removed){
                // Reset relation ID.
                $model
                    ->setRelationId(0)
                    ->save();
                /**
                 * Then create a new client
                 */
                $this->subscribe($event);
            }
        }
    }

    public function unsubscribe(NewsletterEvent $event)
    {
        /** @noinspection PhpParamsInspection */
        $model = MailjetNewsletterQuery::create()->findOneByEmail($event->getEmail());
        if (null !== $model) {
            $unsubscribe = MailjetHelper::getInstance()->unsubscribe($model->getEmail(), $model->getRelationId());
//            if ($unsubscribe) {
//                //TODO :update model?
//            }
        }
    }


    /**
     * @param NewsletterEvent $event
     * @param MailjetNewsletter $model
     * @throws PropelException
     */
    protected function apiAddContactList(NewsletterEvent $event, MailjetNewsletter $model)
    {
        $listName = $this->getContactsListName($event);

        $relationId = MailjetHelper::getInstance()->addContactToList($model->getId(), $model->getRelationId(), $event->getEmail(), $listName);
        // Save the contact/contact-list relation ID, we'll need it for unsubscription.
        if (!empty($relationId)) {
            $model
                ->setRelationId($relationId)
                ->save();
        }
    }

    protected function apiAddUser(NewsletterEvent $event)
    {
        // Check if the email is already registred
        /** @noinspection PhpParamsInspection */
        $model = MailjetNewsletterQuery::create()->findOneByEmail($event->getEmail());

        if (null === $model) {
            $contactId = MailjetHelper::getInstance()->addContact($event->getEmail(), $event->getFirstname(), $event->getLastname());
            if (!empty($contactId)) {
                $model = new MailjetNewsletter();
                try {
                    $model
                        ->setId($contactId)
                        ->setEmail($event->getEmail())
                        ->save();
                } catch (PropelException $e) {
                    Tlog::getInstance()->error($e);
                }
            }
        }
        return $model;
    }



    protected function getEmailFromEvent(NewsletterEvent $event)
    {
        return NewsletterQuery::create()->findPk($event->getId())->getEmail();
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::NEWSLETTER_SUBSCRIBE => array("subscribe", 192), // Come before, as if it crashes, it won't be saved by thelia
            TheliaEvents::NEWSLETTER_UPDATE => array("update", 192),
            TheliaEvents::NEWSLETTER_UNSUBSCRIBE => array("unsubscribe", 192),
        );
    }

    /**
     * @var $event NewsletterEvent
     * @return string the contact list name
     */
    private function getContactsListName($event)
    {
        $mailjetContactsListName = null;
        $locale = $event->getLocale();
        /** @noinspection PhpParamsInspection */
        $lang = empty($locale) ? null : LangQuery::create()->findOneByLocale($locale);
        if (!empty($lang)) {
            /** @var MailjetContactsList $mailjetContactsList */
            /** @noinspection PhpParamsInspection */
            $mailjetContactsList = MailjetContactsListQuery::create()->findByLangId($lang->getId());
            $mailjetContactsListName = empty($mailjetContactsList) ? null : $mailjetContactsList->getName();
            if (!MailjetHelper::getInstance()->isContactsListNameOnAPI($mailjetContactsListName)) {
                throw new Exception(
                    "Cannot retrieve contact list with name ".$mailjetContactsListName.". 
                    Please modify your configuration for locale ".$locale."."
                );
            }
        }
        if (empty($mailjetContactsListName)) {
            $address = ConfigQuery::read(Mailjet::CONFIG_NEWSLETTER_LIST, null);
            $mailjetContactsListName = MailjetHelper::getInstance()->getContactsListNameFromAddress($address);
        }
        if (empty($mailjetContactsListName)) {
            throw new Exception("Cannot retrieve contact list, please check your configuration");
        }
        return $mailjetContactsListName;
    }
}
