<?php


namespace MobileAppForWooCommerce\Controllers;

use Exception;
use Throwable;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

class MediaController
{

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'init']);
    }

    public function init()
    {

        register_rest_route(MAFW_CLIENT_ROUTE, '/media', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_images'],
                'permission_callback' => '__return_true'],
            [
                'methods' => 'POST',
                'callback' => [$this, 'upload_image'],
                'permission_callback' => '__return_true'
            ]
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/media/fix', [
            'methods' => 'POST',
            'callback' => [$this, 'fix'],
            'permission_callback' => '__return_true'
        ]);


    }

    /**
     * @param $image
     * @param $image_path
     * @param $original_width
     * @param $original_height
     * @param $width
     * @param $height
     * @param $type
     */
    static function resize($image, $image_path, $original_width, $original_height, $width, $height, $type)
    {


        if ($type === IMAGETYPE_JPEG) {
            $resized_image = imagescale($image, $width, $height);

            imagejpeg($resized_image, $image_path);

        } else {
            $resized_image = imagecreatetruecolor($width, $height);

            imagealphablending($resized_image, false);

            imagesavealpha($resized_image, true);

            $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);

            imagefilledrectangle($resized_image, 0, 0, $width, $height, $transparent);

            imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $width, $height, $original_width, $original_height);

            imagepng($resized_image, $image_path, 0);

            imagedestroy($resized_image);

            imagedestroy($image);
        }


    }

    /**
     * @param $image_path
     * @throws Exception
     */
    static function logo($image_path)
    {
        if (!function_exists('imagecreatefrompng'))
            return;

        $type = exif_imagetype($image_path);

        if ($type === IMAGETYPE_JPEG)
            $image = imagecreatefromjpeg($image_path);
        else
            $image = imagecreatefrompng($image_path);

        if (!$image)
            throw new Exception('Cannot find image');

        $width = imagesx($image);

        $height = imagesy($image);


        $correct_width = min($width, 109);

        $correct_height = min($height, 32);

        self::resize($image, $image_path, $width, $height, $correct_width, $correct_height, $type);

    }

    /**
     * @param $image_path
     * @throws Exception
     */
    static function make_square($image_path)
    {
        if (!function_exists('imagecreatefrompng'))
            return;

        $type = exif_imagetype($image_path);

        if ($type === IMAGETYPE_JPEG)
            $image = imagecreatefromjpeg($image_path);
        else
            $image = imagecreatefrompng($image_path);

        if (!$image)
            throw new Exception('Cannot find image');

        $width = imagesx($image);

        $height = imagesy($image);

        if ($width === $height)
            return;

        $correct_size = min($width, $height);

        self::resize($image, $image_path, $width, $height, $correct_size, $correct_size, $type);

    }

    /**
     * @param $image_path
     * @throws Exception
     */
    static function clear_transparent($image_path)
    {
        if (!function_exists('imagecreatefrompng'))
            return;

        $type = exif_imagetype($image_path);

        if ($type === IMAGETYPE_JPEG)
            return;

        $image = imagecreatefrompng($image_path);

        if (!$image)
            throw new Exception('Cannot find image');

        $width = imagesx($image);

        $height = imagesy($image);

        $bg = imagecreatetruecolor($width, $height);

        $white = imagecolorallocate($bg, 255, 255, 255);

        imagefill($bg, 0, 0, $white);

        imagecopyresampled(
            $bg, $image,
            0, 0, 0, 0,
            $width, $height,
            $width, $height);


        imagepng($bg, $image_path, 0);

        imagedestroy($image);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_images(WP_REST_Request $request)
    {
        $page = $request->get_param('page');

        $image_types = ['image/png', 'image/jpeg', 'image/jpg'];

        if ($request->get_param('svgEnabled') === 'true')
            $image_types[] = 'image/svg+xml';

        if ($request->get_param('gifEnabled') === 'true')
            $image_types[] = 'image/gif';

        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => $image_types,
            'posts_per_page' => 12,
            'orderby' => 'date',
            'order' => 'DESC',
            'paged' => $page ?? 1
        ];

        if (!empty($request->get_param('search')))
            $args['s'] = $request->get_param('search');

        $media_query = new WP_Query($args);

        $images = [];

        foreach ($media_query->posts as $post) {
            $meta_data = wp_get_attachment_metadata($post->ID);

            if ($request->get_param('gifEnabled') === 'false' and empty($meta_data['width']))
                continue;

            $images[] = ['id' => $post->ID, 'name' => $post->post_title, 'size' => sprintf(" %sx%s", $meta_data['width'], $meta_data['height']), 'url' => wp_get_attachment_url($post->ID), 'thumb' => wp_get_attachment_image_url($post->ID)];
        }

        return new WP_REST_Response(['images' => $images, 'totalItems' => $media_query->found_posts]);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function upload_image(WP_REST_Request $request)
    {

        try {

            require(ABSPATH . 'wp-load.php');

            if (!function_exists('wp_handle_upload'))
                require_once(ABSPATH . 'wp-admin/includes/file.php');

            $files = $request->get_file_params();

            $image = $files['image'];

            if (empty($image))
                throw new Exception('File is not selected.');

            if (!empty($image['error']))
                throw new Exception($image['error']);

            if ($image['size'] > wp_max_upload_size())
                throw new Exception('It is too large than expected.');

            $movefile = wp_handle_upload($image, ['test_form' => false]);

            if (!empty($movefile['error']))
                throw new Exception($movefile['error']);

            $edits = $request->get_param('edits');

            if (!empty($edits)) {

                $edits = explode(',', $edits);

                foreach ($edits as $edit) {

                    if ($edit === 'square')
                        self::make_square($movefile['file']);

                    if ($edit === 'nonTransparent')
                        self::clear_transparent($movefile['file']);

                    if ($edit === 'logo')
                        self::logo($movefile['file']);
                }
            }

            $post_title = preg_replace('/\.[^.]+$/', '', $image['name']);

            $upload_id = wp_insert_attachment(array(
                'guid' => $movefile['file'],
                'post_mime_type' => $movefile['type'],
                'post_title' => $post_title,
                'post_content' => '',
                'post_status' => 'inherit',
            ), $movefile['file']);

            // wp_generate_attachment_metadata() won't work if you do not include this file
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            // Generate and save the attachment metas into the database
            wp_update_attachment_metadata($upload_id, wp_generate_attachment_metadata($upload_id, $movefile['file']));

            $meta_data = wp_get_attachment_metadata($upload_id);

            if ($request->get_param('disableWidthControl') === 'false' and empty($meta_data['width'])) {
                wp_delete_post($upload_id);
                wp_delete_file($movefile['file']);
                throw new Exception('We cannot use this image.Please try another one');
            }

            return new WP_REST_Response(['image' => ['id' => $upload_id, 'name' => $post_title, 'size' => sprintf(' %sx%s', $meta_data['width'], $meta_data['height']), 'url' => wp_get_attachment_url($upload_id), 'thumb' => wp_get_attachment_image_url($upload_id)]]);


        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }

    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function fix(WP_REST_Request $request)
    {
        try {

            $attachment_url = $request->get_param('url');

            $attachment_id = attachment_url_to_postid($attachment_url);

            if (empty($attachment_id))
                throw new Exception('Cannot find attachment');

            $attachment = get_post($attachment_id);

            if (is_null($attachment))
                throw new Exception('Cannot find attachment');

            $uploads = wp_upload_dir();

            $file_type = wp_check_filetype($attachment->guid);

            // create unique file name.
            $filename = wp_unique_filename($uploads['path'], sprintf("%s_shopapper_fix.%s", str_replace('.' . $file_type['ext'], '', $attachment->post_title), $file_type['ext']));

            $new_file_path = $uploads['path'] . "/$filename";

            // copy image.
            if (!copy($attachment->guid, $new_file_path))
                throw new Exception('Cannot copy image');

            $edits = $request->get_param('edits');

            if (!empty($edits)) {


                foreach ($edits as $edit) {

                    if ($edit === 'square')
                        self::make_square($new_file_path);

                    if ($edit === 'nonTransparent')
                        self::clear_transparent($new_file_path);

                    if ($edit === 'logo')
                        self::logo($new_file_path);
                }
            }

            $new_attachment_id = wp_insert_attachment(array(
                'guid' => $new_file_path,
                'post_mime_type' => $file_type['type'],
                'post_title' => $filename,
                'post_content' => '',
                'post_status' => 'inherit',
            ), $new_file_path);

            // wp_generate_attachment_metadata() won't work if you do not include this file
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            // Generate and save the attachment metas into the database
            wp_update_attachment_metadata($new_attachment_id, wp_generate_attachment_metadata($new_attachment_id, $new_file_path));

            $meta_data = wp_get_attachment_metadata($new_attachment_id);

            return new WP_REST_Response(['id' => $new_attachment_id, 'name' => $filename, 'size' => sprintf(' %sx%s', $meta_data['width'], $meta_data['height']), 'url' => wp_get_attachment_url($new_attachment_id), 'thumb' => wp_get_attachment_image_url($new_attachment_id)]);

        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }
}


new MediaController();