<?php
/**
 * @package    storychief
 *
 * @author     StoryChief <support@storychief.io>
 * @copyright  A copyright
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://storychief.io/integrations/joomla
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Component\Content\Administrator\Model\ArticleModel;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Fields\Administrator\Model\FieldModel;
use Joomla\Component\Categories\Administrator\Model\CategoryModel;
use Joomla\Component\Tags\Administrator\Model\TagModel;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\CMS\Helper\MediaHelper;

class StoryHelper
{
    /** @var array */
    protected $data;

    /** @var Registry */
    protected $registry;

    /** @var MVCFactory */
    protected $factory;

    /** @var ArticleModel */
    protected $articles;

    /** @var CategoryModel */
    protected $categories;

    /** @var TagModel */
    protected $tags;

    /** @var FieldModel */
    protected $fields;

    protected $parameterStore;

    public function __construct(array $payload)
    {
        $this->data = $payload["data"];

        $this->registry = ComponentHelper::getParams('com_storychief');

        /** @var MVCFactory $factory */
        $factory = Factory::getApplication()
            ->bootComponent('com_content')
            ->getMVCFactory();

        $this->factory = $factory;

        $this->articles = Factory::getApplication()
            ->bootComponent('com_content')
            ->getMVCFactory()
            ->createModel('Article', 'Administrator', ['ignore_request' => true]);

        $this->categories = Factory::getApplication()
            ->bootComponent('com_categories')
            ->getMVCFactory()
            ->createModel('Category', 'Administrator', ['ignore_request' => true]);

        $this->tags = Factory::getApplication()
            ->bootComponent('com_tags')
            ->getMVCFactory()
            ->createModel('Tag', 'Administrator', ['ignore_request' => true]);

        $this->fields = Factory::getApplication()
            ->bootComponent('com_fields')
            ->getMVCFactory()
            ->createModel('Field', 'Administrator', ['ignore_request' => true]);
    }

    /**
     * @throws Exception
     * @since 1.0
     */
    public function publish(): array
    {
        $fields = [];
        $fields['urls'] = '{"urla":false,"urlatext":"","targeta":"","urlb":false,"urlbtext":"","targetb":"","urlc":false,"urlctext":"","targetc":""}';
        $fields['attribs'] = '{"article_layout":"","show_title":"","link_titles":"","show_intro":"","show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_icons":"","show_print_icon":"","show_email_icon":"","show_vote":"","show_hits":"","show_noauth":"","alternative_readmore":"","show_publishing_options":"","show_article_options":"","show_urls_images_backend":"","show_urls_images_frontend":""}';
        $fields['access'] = 1;
        $fields['language'] = Multilanguage::isEnabled() ? $this->data['language'] : '*';
        $fields['catid'] = $this->findCategoryIdOrDefault();
        $fields['created'] = $this->getCurrentTimestamp();

        $this->mapData($fields);

        // Check to make sure our data is valid, raise notice if it's not.
        if (!$this->articles->save($fields)) {
            throw new Exception($this->articles->getError(), 500);
        }

        $article = $this->articles->getItem();

        $this->mapCustomFields($article);

        return [
            'id' => $this->getStoryId($article),
            'permalink' => $this->getStoryUrl($article),
        ];
    }

    public function update(): array
    {
        $model = $this->articles;

        $fields = [];
        $fields['id'] = $this->data['external_id'];
        $fields['catid'] = $this->findCategoryIdOrDefault();
        $fields['modified'] = $this->getCurrentTimestamp();

        $this->mapData($fields);

        // Check to make sure our data is valid, raise notice if it's not.
        if (!$this->articles->save($fields)) {
            throw new Exception($model->getError(), 500);
        }

        /** @var CMSObject $article */
        $article = $this->articles->getItem($this->data['external_id']);

        $this->mapCustomFields($article);

        return [
            'id' => $this->getStoryId($article),
            'permalink' => $this->getStoryUrl($article),
        ];
    }

    public function delete(): array
    {
        /** @var CMSObject $article */
        $article = $this->articles->getItem($this->data['external_id']);

        if (!$article) {
            throw new Exception('Not found', 404);
        }

        try {
            $db = Factory::getDbo();

            // Delete field values
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__fields_values'))
                ->where($db->quoteName('item_id').' = :item_id')
                ->bind(':item_id', $this->data['external_id'], ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();

            // Change the status to trashed
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__content'))
                ->where($db->quoteName('id').' = :id')
                ->set($db->quoteName('state').' = -2')
                ->bind(':id', $this->data['external_id'], ParameterType::INTEGER)
                ->setLimit(1);

            $db->setQuery($query);
            $db->execute();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), 404);
        }

        return [
            'id' => $this->data['external_id'],
            'permalink' => null,
        ];
    }

    /**
     * Map all default data to content
     */
    protected function mapData(array &$article)
    {
        $authorName = $this->data['author']['data']['first_name']." ".$this->data['author']['data']['last_name'];
        $metaData = [
            'robots' => '',
            'author' => $authorName,
            'rights' => '',
            'xreference' => '',
        ];

        $imagePath = $this->sideloadImage();
        $imageAlt = $this->data['featured_image']['data']['alt'] ?? (null ?: $this->data['title']);
        $imageData = [
            "image_intro" => $imagePath,
            "float_intro" => '',
            "image_intro_alt" => $imageAlt,
            "image_intro_caption" => '',
            "image_fulltext" => $imagePath,
            "float_fulltext" => '',
            "image_fulltext_alt" => $imageAlt,
            "image_fulltext_caption" => '',
        ];

        $article['title'] = $this->data['title'];

        if ($this->data['seo_slug']) {
            $article['alias'] = $this->data['seo_slug'];
        }

        $article['introtext'] = '<p>'.$this->data['excerpt'].'</p>';
        $article['fulltext'] = $this->data['content'];
        $article['state'] = 1;
        $article['created_by_alias'] = $authorName;
        $article['metadesc'] = $this->data['seo_description'] ?: $this->data['excerpt'] ?: '';
        $article['metadata'] = json_encode($metaData);
        $article['images'] = json_encode($imageData);

        if (isset($this->data['tags']['data']) && !empty($this->data['tags']['data'])) {
            foreach ($this->data['tags']['data'] as $tag) {
                $db = Factory::getDbo();
                $query = $db->getQuery(true);

                $constraints = [
                    $db->quoteName('alias').' = '.$db->quote($tag['slug']),
                ];

                $query
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__tags'))
                    ->where($constraints)
                    ->setLimit(1);

                $tagId = $db->setQuery($query)->loadResult();

                if (!$tagId) {
                    $this->tags->save(
                        [
                            'path' => $tag['slug'],
                            'alias' => $tag['slug'],
                            'title' => $tag['name'],
                            'published' => 1,
                            'access' => 1,
                            'description' => '',
                            'language' => '*',
                            'parent_id' => 0,
                        ]
                    );

                    // Save new created tag
                    $article['tags'][] = $this->tags->getItem()->get('id');
                } else {
                    $article['tags'][] = $tagId;
                }
            }
        }
    }

    /**
     * Map any defined custom fields
     */
    protected function mapCustomFields(CMSObject $article): void
    {
        $mapping =  $this->registry->get('field_mapping', new stdClass());

        foreach ($mapping as $field) {
            if (isset($field->field_joomla_id, $field->field_storychief_id)) {
                $valueKey = array_search($field->field_storychief_id, array_column($this->data['custom_fields'], 'key'));
                $fieldValue = $this->data['custom_fields'][$valueKey]['value'] ?? null;

                $this->fields->setFieldValue($field->field_joomla_id, $article->get('id'), $fieldValue);
            }
        }
    }

    /**
     * Get the ID of the story
     */
    protected function getStoryId(CMSObject $article): int
    {
        return $article->get('id');
    }

    /**
     * Get the permalink of the story
     */
    protected function getStoryUrl(CMSObject $article): string
    {
        return Route::_(
            Uri::root().RouteHelper::getArticleRoute(
                $article->get('id'),
                $article->get('catid') ?: 0,
                $article->get('language') ?: 0
            )
        );
    }

    /**
     * Sideload an image
     *
     * @throws Exception
     */
    protected function sideloadImage(): ?string
    {
        if (!isset($this->data['featured_image']['data']['sizes']['full'])) {
            return '';
        }

        $extFileUri = $this->data['featured_image']['data']['sizes']['full'];
        $extFilename = strtolower($this->data['featured_image']['data']['name']);
        $tmpFileLocation = JPATH_ROOT.'/tmp'.DIRECTORY_SEPARATOR.$extFilename;

        // download the image in tmp folder
        file_put_contents($tmpFileLocation, fopen($extFileUri, 'r'));

        $file = [
            'name' => str_replace(' ', '-', File::makeSafe($extFilename)),
            'type' => mime_content_type($tmpFileLocation),
            'size' => filesize($tmpFileLocation),
            'tmp_name' => $tmpFileLocation,
            'error' => 0
        ];

        $mediaHelper = new MediaHelper();

        if (!$mediaHelper->canUpload($file)) {
            throw new Exception("Unable to upload image \"$extFilename\"", 500);
        }

        // prepare the uploaded file's destination pathnames
        $relativePath = Path::clean('images'.DIRECTORY_SEPARATOR.'storychief');
        $absolutePath = JPATH_ROOT.DIRECTORY_SEPARATOR.$relativePath;

        if (!Folder::exists($absolutePath)) {
            if (!Folder::create($absolutePath)) {
                throw new Exception("Failed creating assets path \"$absolutePath\"", 500);
            }
        }

        $relativeFilePath = $relativePath.DIRECTORY_SEPARATOR.$file['name'];
        $absoluteFilePath = JPATH_ROOT.DIRECTORY_SEPARATOR.$relativeFilePath;

        if (!File::exists($absoluteFilePath)) {
            if (!copy($file['tmp_name'], $absoluteFilePath)) {
                throw new Exception("Failed uploading image \"$extFilename\"", 500);
            }
        }

        unlink($tmpFileLocation);

        return $relativeFilePath;
    }

    /**
     * Find the proper category by the payload's data or return a default category if non existing
     *
     * @throws Exception
     */
    protected function findCategoryIdOrDefault(): int
    {
        $category = $this->data['category']['data'] ?? null;
        $categoryId = null;

        if ($category) {
            $db = Factory::getDbo();
            $query = $db->getQuery(true);

            $constraints = [
                $db->quoteName('extension').' = '.$db->quote('com_content'),
                $db->quoteName('path').' = '.$db->quote($category['slug']),
                $db->quoteName('access').' = 1',
                $db->quoteName('published').' = 1',
            ];

            $query
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__categories'))
                ->where($constraints)
                ->setLimit(1);

            $categoryId = $db->setQuery($query)->loadResult();

            if (!$categoryId) {
                $this->categories->save(
                    [
                        'path' => $category['slug'],
                        'alias' => $category['slug'],
                        'title' => $category['name'],
                        'extension' => 'com_content',
                        'published' => 1,
                        'access' => 1,
                        'level' => 0,
                        'description' => '',
                        'language' => '*',
                    ]
                );

                $categoryId = $this->categories->getItem()->get('id');
            }
        } else {
            $categoryId = $this->parameterStore->get('default_category');
        }

        if (!$categoryId) {
            throw new Exception("Sorry, no category was passed or a default was set in the StoryChief extension configuration.");
        }

        return $categoryId;
    }

    protected function getCurrentTimestamp(): string
    {
        return Factory::getDate()->toSQL();
    }
}
