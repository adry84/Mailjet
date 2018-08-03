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

namespace Mailjet;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Finder\Finder;
use Thelia\Install\Database;
use Thelia\Model\Config;
use Thelia\Model\ConfigQuery;
use Thelia\Module\BaseModule;

/**
 * Class Mailjet
 * @package Mailjet
 * @author Benjamin Perche <bperche@openstudio.com>
 */
class Mailjet extends BaseModule
{
    const MESSAGE_DOMAIN = "mailjet";

    const CONFIG_NEWSLETTER_LIST = "mailjet.newsletter_list";
    const CONFIG_API_KEY = "mailjet.api.key";
    const CONFIG_API_SECRET = "mailjet.api.secret";
    const CONFIG_API_WS_ADDRESS = "mail.api.webservice_address";
    const CONFIG_THROW_EXCEPTION_ON_ERROR = "mailjet.throw_exception_on_error";


    public function postActivation(ConnectionInterface $con = null)
    {
        $con->beginTransaction();

        try {
            if (null === ConfigQuery::read(static::CONFIG_API_KEY)) {
                $this->createConfigValue(static::CONFIG_API_KEY, [
                    "fr_FR" => "Clé d'API pour mailjet",
                    "en_US" => "Api key for mailjet",
                ]);
            }

            if (null === ConfigQuery::read(static::CONFIG_API_SECRET)) {
                $this->createConfigValue(static::CONFIG_API_SECRET, [
                    "fr_FR" => "Secret d'API pour mailjet",
                    "en_US" => "Api secret for mailjet",
                ]);
            }

            if (null === ConfigQuery::read(static::CONFIG_NEWSLETTER_LIST)) {
                $this->createConfigValue(static::CONFIG_NEWSLETTER_LIST, [
                    "fr_FR" => "ALT de la liste de diffusion mailjet par défaut",
                    "en_US" => "Default diffusion list ALT of mailjet",
                ]);
            }

            if (null === ConfigQuery::read(static::CONFIG_API_WS_ADDRESS)) {
                $this->createConfigValue(
                    static::CONFIG_API_WS_ADDRESS,
                    [
                        "fr_FR" => "Adresse du webservice mailjet",
                        "en_US" => "Address of the mailjet webservice",
                    ],
                    "https://api.mailjet.com/v3/REST"
                );
            }

            $database = new Database($con);
            $database->insertSql(null, [__DIR__ . "/Config/thelia.sql"]);

            $con->commit();
        } catch (\Exception $e) {
            $con->rollBack();

            throw $e;
        }
    }

    protected function createConfigValue($name, array $translation, $value = '')
    {
        $config = new Config();
        $config
            ->setName($name)
            ->setValue($value)
        ;

        foreach ($translation as $locale => $title) {
            $config->getTranslation($locale)
                ->setTitle($title)
            ;
        }

        $config->save();
    }

    public function update($currentVersion, $newVersion, ConnectionInterface $con = null)
    {
        $finderSql = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in(__DIR__ . DS . 'Config'. DS .'sql'. DS . 'update');

        $database = new Database($con);

        /** @var \SplFileInfo $file */
        foreach ($finderSql as $file) {
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }
    }
}
