<?php
/**
 * @package    storychief
 *
 * @author     StoryChief <support@storychief.io>
 * @copyright  A copyright
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://storychief.io/integrations/joomla
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Response\JsonResponse;

defined('_JEXEC') or die;

class StorychiefController extends BaseController
{
    public function webhook()
    {
        try {
            $payload = json_decode(file_get_contents('php://input'), true);

            if (!$this->validatePayload($payload)) {
                throw new Exception('Invalid mac', 422);
            }

            $articles = new StoryHelper($payload);
            $event = $payload['meta']['event'];

            switch ($event) {
                case 'publish':
                    $data = $articles->publish();
                    break;
                case 'update':
                    $data = $articles->update();
                    break;
                case 'delete':
                    $data = $articles->delete();
                    break;
                case 'test':
                    $data = null;
                    break;
                default:
                    throw new Exception("Unknown event: \"$event\"", 500);
            }

            $this->jsonRespond($data);
        } catch (Exception $e) {
            $data = new JsonResponse($e);
            $this->jsonRespond($data, $e->getCode());
        }
    }

    protected function jsonRespond($data, $code = 200)
    {
        $knownStatusCodes = [200, 404, 422, 500];

        /** @var SiteApplication $app */
        $app = Factory::getApplication();
        $app->mimeType = 'application/json';
        $app->setHeader('Content-Type', $app->mimeType.'; charset='.$app->charSet);
        $app->setHeader('Status', in_array($code, $knownStatusCodes) ? $code : 500);
        $app->sendHeaders();

        if (!is_string($data)) {
            $data = json_encode($data);
        }

        echo $data;

        $app->close();
    }

    protected function validatePayload($payload): bool
    {
        if (isset($payload['meta']['mac'])) {
            $params = ComponentHelper::getParams('com_storychief');
            $givenMac = $payload['meta']['mac'];
            unset($payload['meta']['mac']);
            $calcMac = hash_hmac('sha256', json_encode($payload), $params->get('encryption_key'));

            return hash_equals($givenMac, $calcMac);
        }

        return false;
    }
}
