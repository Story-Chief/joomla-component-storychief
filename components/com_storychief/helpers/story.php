<?php
/**
 * @package    storychief
 *
 * @author     Greg <your@email.com>
 * @copyright  A copyright
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://your.url.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Multilanguage;

/**
 * Story Helper
 *
 * @since  1.0
 */
class StoryHelper {

    protected $data, $story, $parameterStore;

    public function __construct(array $payload) {
        $this->data = $payload["data"];
        $this->parameterStore = JComponentHelper::getParams('com_storychief');
        if (version_compare(JVERSION, '3.0', 'lt')) {
            JTable::addIncludePath(JPATH_PLATFORM . 'joomla/database/table');
        }
        $this->story = JTable::getInstance('content');
    }

    public function publish() {
        $this->story->urls = '{"urla":false,"urlatext":"","targeta":"","urlb":false,"urlbtext":"","targetb":"","urlc":false,"urlctext":"","targetc":""}';
        $this->story->attribs = '{"article_layout":"","show_title":"","link_titles":"","show_intro":"","show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_icons":"","show_print_icon":"","show_email_icon":"","show_vote":"","show_hits":"","show_noauth":"","alternative_readmore":"","show_publishing_options":"","show_article_options":"","show_urls_images_backend":"","show_urls_images_frontend":""}';
        $this->story->access = 1;
        $this->story->language = Multilanguage::isEnabled() ? $this->data['language'] : '*';
        $this->story->catid = $this->findCategoryIdOrDefault();
        $this->story->created = JFactory::getDate()->toSQL();

        $this->mapData();

        // Check to make sure our data is valid, raise notice if it's not.
        if (!$this->story->check() || !$this->story->store(true)) {
            throw new Exception($this->story->getError(), 500);
        }

        $this->mapCustomFields();

        return [
            'id'        => $this->getStoryId(),
            'permalink' => $this->getStoryUrl(),
        ];
    }

    public function update() {
        $this->story->load($this->data['external_id']);

        $this->story->modified = JFactory::getDate()->toSQL();

        $this->mapData();

        // Check to make sure our data is valid, raise notice if it's not.
        if (!$this->story->check() || !$this->story->store(false)) {
            throw new Exception($this->story->getError(), 500);
        }

        $this->mapCustomFields();

        return [
            'id'        => $this->getStoryId(),
            'permalink' => $this->getStoryUrl(),
        ];
    }

    public function delete() {
        try {
            $this->story->load($this->data['external_id']);
            $this->deleteCustomFieldValue('all');
            $this->story->delete();
        } catch (Exception $e) {
            throw new Exception('Not found', 404);
        }

        return [
            'id'        => $this->data['external_id'],
            'permalink' => null,
        ];
    }

    /**
     * Map all default data to content
     */
    protected function mapData() {
        $author_name = $this->data['author']['data']['first_name'] . " " . $this->data['author']['data']['last_name'];
        $meta_data = [
            'robots'     => '',
            'author'     => $author_name,
            'rights'     => '',
            'xreference' => '',
        ];

        $image_path = $this->sideloadImage();
        $image_data = [
            "image_intro"            => $image_path,
            "float_intro"            => '',
            "image_intro_alt"        => $this->data['title'],
            "image_intro_caption"    => '',
            "image_fulltext"         => $image_path,
            "float_fulltext"         => '',
            "image_fulltext_alt"     => $this->data['title'],
            "image_fulltext_caption" => '',
        ];

        $this->story->title = $this->data['title'];
        if($this->data['seo_slug']){
            $this->story->alias = $this->data['seo_slug'];
        }
        $this->story->introtext = '<p>'.$this->data['excerpt'].'</p>';
        $this->story->fulltext = $this->data['content'];
        $this->story->state = 1;
        $this->story->created_by_alias = $author_name;
        $this->story->metadesc = $this->data['seo_description'] ?: ['$this->data->excerpt'];
        $this->story->metadata = json_encode($meta_data);
        $this->story->images = json_encode($image_data);
    }

    /**
     * Map any defined custom fields
     */
    protected function mapCustomFields() {
        $mapping = json_decode($this->parameterStore->get('field_mapping'), true);

        if (!empty($mapping) && isset($mapping['field_joomla_id'])) {
            foreach ($mapping['field_joomla_id'] as $key => $field_id) {
                if (isset($mapping['field_storychief_id'][$key]) && !empty($mapping['field_storychief_id'][$key])) {
                    $mapping_key = $mapping['field_storychief_id'][$key];
                    if ($field_id && $mapping_key) {
                        $value_key = array_search($mapping_key, array_column($this->data['custom_fields'], 'key'));
                        $field_value = $this->data['custom_fields'][$value_key]['value'];
                        $this->deleteCustomFieldValue($field_id);
                        $this->saveCustomFieldValue($field_id, $field_value);
                    }
                }
            }
        }
    }

    /**
     * Delete a custom field value
     *
     * @param int|'all' $field_id
     */
    protected function deleteCustomFieldValue($field_id) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        // delete any old value
        $constraints = [
            $db->quoteName('item_id') . ' = ' . $this->story->id,
        ];
        if ($field_id !== 'all') {
            $constraints[] = $db->quoteName('field_id') . ' = ' . $field_id;
        }

        $query->delete($db->quoteName('#__fields_values'))->where($constraints);
        $db->setQuery($query)->execute();
    }

    /**
     * Save a custom field value
     *
     * @param $field_id
     * @param $field_value
     */
    protected function saveCustomFieldValue($field_id, $field_value) {
        $db = JFactory::getDbo();

        // insert new value
        $field = new stdClass();
        $field->field_id = $field_id;
        $field->item_id = $this->story->id;
        $field->value = $field_value;
        $db->insertObject('#__fields_values', $field);
    }

    /**
     * Get the ID of the story
     *
     * @return mixed
     */
    protected function getStoryId() {
        return $this->story->id;
    }

    /**
     * Get the permalink of the story
     *
     * @return mixed
     */
    protected function getStoryUrl() {
        return JUri::root() . trim(JRoute::_(ContentHelperRoute::getArticleRoute($this->getStoryId(), $this->story->catid, $this->story->language)), '/');
    }

    /**
     * Sideload an image
     *
     * @return string
     * @throws Exception
     */
    protected function sideloadImage() {
        if (!isset($this->data['featured_image']['data']['sizes']['full'])) return '';

        $ext_file_uri = $this->data['featured_image']['data']['sizes']['full'];
        $ext_filename = strtolower($this->data['featured_image']['data']['name']);
        $tmp_file_location = JFactory::getApplication()->get('tmp_path') . DIRECTORY_SEPARATOR . $ext_filename;

        // download the image in tmp folder
        file_put_contents($tmp_file_location, fopen($ext_file_uri, 'r'));

        $file = [
            'name'     => str_replace(' ', '-', JFile::makeSafe($ext_filename)),
            'type'     => mime_content_type($tmp_file_location),
            'size'     => filesize($tmp_file_location),
            'tmp_name' => $tmp_file_location,
            'error'    => 0
        ];

        $media_helper = new JHelperMedia;
        if (!$media_helper->canUpload($file)) {
            throw new Exception("Unable to upload image \"$ext_filename\"", 500);
        }

        // prepare the uploaded file's destination pathnames
        $relative_path = JPath::clean('images' . DIRECTORY_SEPARATOR . 'storychief');
        $absolute_path = JPATH_ROOT . DIRECTORY_SEPARATOR . $relative_path;

        if (!JFolder::exists($absolute_path)) {
            if (!JFolder::create($absolute_path)) {
                throw new Exception("Failed creating assets path \"$absolute_path\"", 500);
            }
        }
        $relative_file_path = $relative_path . DIRECTORY_SEPARATOR . $file['name'];
        $absolute_file_path = JPATH_ROOT . DIRECTORY_SEPARATOR . $relative_file_path;

        if (!JFile::exists($absolute_file_path)) {
            if (!copy($file['tmp_name'], $absolute_file_path)) {
                throw new Exception("Failed uploading image \"$ext_filename\"", 500);
            }
        }

        unlink($tmp_file_location);

        return $relative_file_path;
    }

    /**
     * Find the proper category by the payload's data or return a default category if non existing
     *
     * @return mixed
     */
    protected function findCategoryIdOrDefault() {
        $category_name = isset($this->data['category']['data']['name']) ? $this->data['category']['data']['name'] : null;

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $constraints = [
            $db->quoteName('extension') . ' = ' . $db->quote('com_content'),
            $db->quoteName('title') . ' = ' . $db->quote($category_name),
            $db->quoteName('access') . ' = 1',
            $db->quoteName('published') . ' = 1',
        ];

        /*
        if (Multilanguage::isEnabled()) {
            $constraints[] = $db->quoteName('language') . ' = ' . $db->quote($this->data['language']);
        } else {
            $constraints[] = $db->quoteName('language') . ' = ' . $db->quote('*');
        }
        */

        $query
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($constraints)
            ->setLimit(1);

        $result = $db->setQuery($query)->loadResult();

        if (is_null($result)) {
            $result = $this->parameterStore->get('default_category');
        }

        return $result;
    }
}
