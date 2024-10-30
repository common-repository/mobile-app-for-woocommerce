<?php

class YITHQuestionAnswer
{
    function __construct()
    {

        add_action('rest_api_init', [$this, 'init']);
    }

    public function init()
    {

        register_rest_route(MAFW_CLIENT_ROUTE, '/yith-questions-and-answers/questions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_questions'],
            'args' => [
                'productId' => ['required' => true]
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/yith-questions-and-answers/questions/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_question'],
            'args' => [
                'productId' => ['required' => true],
                'text' => ['required' => true],
                'consumer_key' => ['required' => true],
                'consumer_secret' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/yith-questions-and-answers/answers/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_answer'],
            'args' => [
                'productId' => ['required' => true],
                'questionId' => ['required' => true],
                'text' => ['required' => true],
                'consumer_key' => ['required' => true],
                'consumer_secret' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

    }

    public function get_author_data($id, $name, $email): array
    {
        if (empty($id))
            return [
                'author_id' => 0,
                'author_name' => $name,
                'author_email' => $email
            ];

        $author = get_userdata($id);

        return [
            'author_id' => $id,
            'author_name' => empty($name) ? $author->display_name : $name,
            'author_email' => empty($email) ? $author->user_email : $email,
        ];

    }

    public function get_questions(WP_REST_Request $request): WP_REST_Response
    {
        try {

            $yith_questions = YWQA()->get_questions($request->get_param('productId'));

            $questions = [];

            foreach ($yith_questions as $yith_question) {

                $question = array_merge([
                    'id' => $yith_question->ID,
                    'content' => $yith_question->content,
                    'date' => $yith_question->date,
                    'answers' => []
                ], $this->get_author_data($yith_question->discussion_author_id, $yith_question->discussion_author_name, $yith_question->discussion_author_email));

                foreach ($yith_question->get_answers() as $answer) {
                    $question['answers'][] = array_merge([
                        'id' => $answer->ID,
                        'content' => $answer->content,
                        'date' => $answer->date,
                    ], $this->get_author_data($answer->discussion_author_id, $answer->discussion_author_name, $answer->discussion_author_email));
                }

                $questions[] = $question;
            }


            return new WP_REST_Response($questions);

        } catch (Throwable $e) {
            return new WP_REST_Response($e->getMessage(), 500);
        }
    }

    public function submit_question(WP_REST_Request $request): WP_REST_Response
    {
        try {

            if(!is_user_logged_in())
                throw new Exception('Bad Authentication');

            $product_id = intval(wp_unslash($request->get_param('productId')));

            $text = stripslashes(implode("\n", array_map('sanitize_text_field', explode("\n", sanitize_text_field(wp_unslash($request->get_param('text')))))));

            $args = array(
                'content' => $text,
                'discussion_author_id' => get_current_user_id(),
                'product_id' => $product_id,
                'parent_id' => $product_id,
            );

            YWQA()->create_question($args);

            return new WP_REST_Response('Question successfully submitted.');

        } catch (Throwable $e) {
            return new WP_REST_Response($e->getMessage(), 500);
        }
    }

    public function submit_answer(WP_REST_Request $request): WP_REST_Response
    {
        try {

            if(!is_user_logged_in())
                throw new Exception('Bad Authentication');

            $product_id = intval(wp_unslash($request->get_param('productId')));

            $question_id = intval(wp_unslash($request->get_param('questionId')));

            $text = stripslashes(implode("\n", array_map('sanitize_text_field', explode("\n", sanitize_text_field(wp_unslash($request->get_param('text')))))));

            $args = array(
                'content' => $text,
                'discussion_author_id' => get_current_user_id(),
                'product_id' => $product_id,
                'parent_id' => $question_id,
            );

            YWQA()->create_answer($args);

            return new WP_REST_Response('Answer successfully submitted');
        } catch (Throwable $e) {
            return new WP_REST_Response($e->getMessage(), 500);
        }
    }
}

new YITHQuestionAnswer();