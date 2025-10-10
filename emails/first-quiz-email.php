<?php
/**
 * Shared logic for the "First Quiz" transactional email.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'woo_check_prepare_first_quiz_email_data' ) ) {
    /**
     * Prepare the metrics used by the "First Quiz" email template.
     *
     * @param int        $quiz_post_id Quiz identifier.
     * @param float|null $user_score   Normalized user score.
     * @return array{average_score:float|null,user_score:float|null}
     */
    function woo_check_prepare_first_quiz_email_data( int $quiz_post_id, ?float $user_score ): array {
        $average_score = null;

        if ( $quiz_post_id ) {
            if ( class_exists( 'Polis_Quiz_Average_Helper' ) ) {
                sleep( 1 );
                $average_score = Polis_Quiz_Average_Helper::get_average_for_quiz( $quiz_post_id );
                error_log( '[FirstQuizEmail] Average via Polis_Quiz_Average_Helper=' . print_r( $average_score, true ) );
            } elseif ( class_exists( 'Polis_Quiz_Attempts_Shortcode' ) && method_exists( 'Polis_Quiz_Attempts_Shortcode', 'get_polis_average_for_quiz' ) ) {
                sleep( 1 );
                $average_score = Polis_Quiz_Attempts_Shortcode::get_polis_average_for_quiz( $quiz_post_id );
                error_log( '[FirstQuizEmail] Average via Polis_Quiz_Attempts_Shortcode=' . print_r( $average_score, true ) );
            } elseif ( class_exists( 'Villegas_Quiz_Stats' ) && method_exists( 'Villegas_Quiz_Stats', 'get_average_percentage' ) ) {
                sleep( 1 );
                $average_score = Villegas_Quiz_Stats::get_average_percentage( $quiz_post_id );
                error_log( '[FirstQuizEmail] Average via Villegas_Quiz_Stats=' . print_r( $average_score, true ) );
            }
        }

        error_log( '[FirstQuizEmail] Average (raw)=' . print_r( $average_score, true ) );
        error_log( '[FirstQuizEmail] User score (normalized)=' . print_r( $user_score, true ) );

        return [
            'average_score' => $average_score,
            'user_score'    => $user_score,
        ];
    }
}
