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

namespace Mailjet\Controller;

use Mailjet\Form\MailjetConfigurationForm;
use Mailjet\Form\MailjetContactsListsForm;
use Mailjet\Mailjet;
use Mailjet\Model\MailjetContactsList;
use Mailjet\Model\MailjetContactsListQuery;
use Mailjet\Model\Map\MailjetContactsListTableMap;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Acl\Exception\Exception;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

/**
 * Class MailjetConfigController
 * @package Mailjet\Controller
 * @author Benjamin Perche <bperche@openstudio.com>
 */
class MailjetConfigController extends BaseAdminController
{
    public function saveAction()
    {
        $configurationType = $this->getRequest()->get("mailjet_configuration_type");
        if ($configurationType === "mailjet_api") {
            $this->saveMailjetConfiguration();
        } else  if ($configurationType === "contacts_list") {
            $this->saveMailjetLangContactsLists();
        }
        if ("close" === $this->getRequest()->request->get("save_mode")) {
            return new RedirectResponse(URL::getInstance()->absoluteUrl("/admin/modules"));
        }
        return $this->render('module-configure', [ 'module_code' => 'Mailjet' ]);
    }

    private function saveMailjetLangContactsLists()
    {
        $baseForm = new MailjetContactsListsForm($this->getRequest());
        $baseForm->setErrorMessage(null);
        $con = null;
        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();
            /** @var Lang[] $langs */
            $langs = LangQuery::create()->filterByActive(true)->find();
            if (empty($langs)) {
                throw new Exception("No active languages");
            }
            $con = Propel::getServiceContainer()->getWriteConnection(MailjetContactsListTableMap::DATABASE_NAME);
            $con->beginTransaction();
            foreach ($langs as $lang) {
                $fieldName = "contactsList" . $lang->getId();
                $mailjetContactsList = MailjetContactsListQuery::create()->filterByLangId($lang->getId())->findOne();
                if (empty($mailjetContactsList)) {
                    $mailjetContactsList = new MailjetContactsList();
                    $mailjetContactsList->setLangId($lang->getId());
                }
                $mailjetContactsList->setName($data[$fieldName]);
                $mailjetContactsList->save();
            }
            $con->commit();
            $this->getParserContext()->set("success_contacts_lists", true);
            $this->getParserContext()->set("current_tab", "mailjet-contacts-lists");


        } catch (\Exception $e) {
            if (!empty($con)) {
                $con->rollBack();
            }
            $baseForm->setErrorMessage($e->getMessage());
            $this->getParserContext()
                ->setGeneralError($e->getMessage())
                ->addForm($baseForm)
            ;
        }
    }

    private function saveMailjetConfiguration()
    {
        $baseForm = new MailjetConfigurationForm($this->getRequest());
        $baseForm->setErrorMessage(null);
        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();

            ConfigQuery::write(Mailjet::CONFIG_API_KEY, $data["api_key"]);
            ConfigQuery::write(Mailjet::CONFIG_API_SECRET, $data["api_secret"]);
            ConfigQuery::write(Mailjet::CONFIG_API_WS_ADDRESS, $data["ws_address"]);
            ConfigQuery::write(Mailjet::CONFIG_NEWSLETTER_LIST, $data["newsletter_list"]);
            ConfigQuery::write(Mailjet::CONFIG_THROW_EXCEPTION_ON_ERROR, $data["exception_on_errors"] ? true : false);

            $this->getParserContext()->set("success_mailjet_config", true);
            $this->getParserContext()->set("current_tab", "mailjet-config");

        } catch (\Exception $e) {
            $baseForm->setErrorMessage($e->getMessage());
            $this->getParserContext()
                ->setGeneralError($e->getMessage())
                ->addForm($baseForm)
            ;
        }
    }
}
