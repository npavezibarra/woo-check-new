<?php
/**
 * Shared helper for retrieving the filtered Polis quiz average.
 */

if ( ! class_exists( 'Polis_Quiz_Average_Helper' ) ) {
    class Polis_Quiz_Average_Helper {
        /**
         * Fetch the most up-to-date quiz average using the same logic as the frontend shortcode.
         *
         * @param int $quiz_id Quiz identifier.
         * @return float|null Average percentage or null when unavailable.
         */
        public static function get_average_for_quiz( int $quiz_id ): ?float {
            if ( $quiz_id <= 0 ) {
                return null;
            }

            if ( class_exists( 'Polis_Quiz_Attempts_Shortcode' ) && method_exists( 'Polis_Quiz_Attempts_Shortcode', 'get_polis_average_for_quiz' ) ) {
                return Polis_Quiz_Attempts_Shortcode::get_polis_average_for_quiz( $quiz_id );
            }

            if ( class_exists( 'Villegas_Quiz_Stats' ) && method_exists( 'Villegas_Quiz_Stats', 'get_average_percentage' ) ) {
                return Villegas_Quiz_Stats::get_average_percentage( $quiz_id );
            }

            return null;
        }
    }
}
