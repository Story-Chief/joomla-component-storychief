# STORYCHIEF

Connect your Joomla! website to [StoryChief](https://storychief.io/) and publish straight to your website.

## PUBLISH BETTER CONTENT

Gain better collaboration, create more interactive and beautiful content with [StoryChief](https://storychief.io/) and go Multi-Channel without any hurdles or technical requirements.

## This component

- Publish articles straight from StoryChief
- Keeps your formatting like header tags, bold, links, lists etc
- Does not alter your website’s branding, by using your site’s CSS for styling
- Imports text and images from your StoryChief story
- Supports custom fields
- Support multi-language
- Supports categories, tags

## HOW IT WORKS

1. [Register](https://app.storychief.io/register) on StoryChief
2. Add a 'Joomla' channel
3. Set the webhook to: https://mywebsite.com/
4. [Download](https://github.com/Story-Chief/joomla-component-storychief/releases/latest) & install the component
5. Configure the component by saving your encryption key from StoryChief
6. Publish from Story Chief to your Joomla! website

## REQUIREMENTS
- This component requires a StoryChief account.
    - Not a Story Chief user yet? [Sign up](https://app.storychief.io/register) for free!
- Joomla! 4.0 or higher
- PHP version 7.4 or higher

If you want to use StoryChief for your **Joomla 3** website, instead please use the latest v3 release

## FAQs

### New tags aren't added to the articles

You need to give public permission to create tags.

Navigate to: "System" -> "Global Configuration" -> "Tags" -> "Permissions".

For the public group set "create" to "allowed" for public and save the configuration.

### Custom fields aren't saved

You need to give public permission to save fields.

Navigate to: "System" -> "Global Configuration" -> "Articles" -> "Permissions".

For the public group set "Edit Custom Field Value" to "allowed" for public and save the configuration.
