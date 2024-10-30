<?php
/**
 * Created by PhpStorm.
 * User: janhenkes
 * Date: 01/10/16
 * Time: 22:21
 */

namespace JmSocialMediaAutoPublisher;

class Publisher {
    const event_publish = 'jm_smap_publish';
    const event_visit = 'jm_smap_visit_post';
    const medium_facebook = 'facebook';
    const medium_linkedin = 'linkedin';
    const medium_twitter = 'twitter';
    const medium_yammer = 'yammer';
    const type_user = 'user';
    const type_page = 'page';

    public static function unschedule_publishes( $post_id, $options, $force_replubish = true ) {
        wp_clear_scheduled_hook( self::event_visit, [
            $post_id,
        ] );

        if ( isset( $options['facebook_account'] ) ) {
            wp_clear_scheduled_hook( self::event_publish, [
                $post_id,
                self::medium_facebook,
                'me',
            ] );
        }

        foreach ( $options['facebook_pages'] as $facebook_page ) {
            wp_clear_scheduled_hook( self::event_publish, [
                $post_id,
                self::medium_facebook,
                $facebook_page,
            ] );
        }

        if ( isset( $options['linkedin_account'] ) ) {
            wp_clear_scheduled_hook( self::event_publish, [
                $post_id,
                self::medium_linkedin,
                '',
                self::type_user,
            ] );
        }

        foreach ( $options['linkedin_pages'] as $linkedin_page ) {
            wp_clear_scheduled_hook( self::event_publish, [
                $post_id,
                self::medium_linkedin,
                $linkedin_page,
                self::type_page,
            ] );
        }

        if ( isset( $options['twitter_account'] ) ) {
            wp_clear_scheduled_hook( self::event_publish, [
                $post_id,
                self::medium_twitter,
            ] );
        }

        if ( isset( $options['yammer_network'] ) ) {
            wp_clear_scheduled_hook( self::event_publish, [
                $post_id,
                self::medium_yammer,
            ] );
        }

        if ( $force_replubish ) {
            update_post_meta( $post_id, 'jm_smap_published', 0 );
        }
    }

    public static function schedule_publishes( $post_id, $post, $update ) {
        global $sitepress;

        $options = Admin::get_options();

        Plugin::logger_instance()->info( "Saving post with id {$post_id}." );

        /**
         * Only post the posts in default language
         */
        if ( function_exists( 'wpml_get_language_information' ) ) {
            Plugin::logger_instance()->info( "This website uses WPML." );
            $language_information = wpml_get_language_information( null, $post_id );
            $default_language     = $sitepress->get_default_language();
            $current_language     = $language_information['language_code'] ?: $_GET['lang'];
            if ( $current_language != null && $current_language != $default_language ) {
                Plugin::logger_instance()
                      ->info( "This post is not in the main language so we are not publishing it to social media." );

                return;
            }
        }

        /**
         * ! $update => $update is true als er een nieuwe post wordt aangemaakt, voordat je hem gepubliceerd hebt
         * wp_is_post_revision( $post_id ) => post mag geen revision zijn
         * ! in_array( $post->post_type, $options['post_types'] ) => post type moet in de admin aangevinkt zijn
         * $post->post_status != 'publish' => de post moet wel gepubliceerd zijn
         */
        if ( ! $update || wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, $options['post_types'] ) || ! in_array( $post->post_status, [
                'publish',
                'future',
            ] ) || strlen( $post->post_password ) ) {
            /**
             *
             */
            Plugin::logger_instance()
                  ->info( "Not going to publish to social media because it is a revision, it is not in the post type array, not in the post_status array or secured with a password." );
            if ( in_array( $post->post_status, [ 'draft', 'pending', 'private' ] ) || strlen( $post->post_password ) ) {
                Plugin::logger_instance()->info( "Unscheduling publishes" );
                self::unschedule_publishes( $post_id, $options, true );
            }

            return;
        }

        $published = isset( $_POST['jm_smap_published'] ) && $_POST['jm_smap_published'] == "1";
        Plugin::logger_instance()->info( "Is the post published before: {$published}" );

        if ( $published ) {
            $any_scheduled_events = self::has_any_event_scheduled( $post_id, $options );
            if ( ! empty( $any_scheduled_events ) ) {
                $published = false;
            }
        }

        if ( ! $published ) {
            self::unschedule_publishes( $post_id, $options );
            Plugin::logger_instance()->info( "Unscheduling publishes" );

            $time       = time() + 60 * 60;
            $time_visit = time() + 60 * 50;

            if ( $post->post_status == 'future' ) {
                $time = get_post_time( 'U', true, $post );
                $time = $time + 60 * 60;
            }

            $readable_date = date_i18n( "Y-m-d H:i:s", $time );
            Plugin::logger_instance()->info( "Schedule time: {$time} ({$readable_date})" );

            $next_schedule_visit = wp_next_scheduled( self::event_visit, [ $post_id ] );
            if ( ! $next_schedule_visit ) {
                wp_schedule_single_event( $time_visit, self::event_visit, [ $post_id ] );
                Plugin::logger_instance()->info( "Scheduled visit event" );
            }

            if ( isset( $options['facebook_account'] ) ) {
                $args          = [
                    $post_id,
                    self::medium_facebook,
                    'me',
                ];
                $next_schedule = wp_next_scheduled( self::event_publish, $args );
                if ( ! $next_schedule ) {
                    wp_schedule_single_event( $time, self::event_publish, $args );
                    Plugin::logger_instance()->info( "Scheduled Facebook account publish event" );
                }
            }

            foreach ( $options['facebook_pages'] as $facebook_page ) {
                $args          = [
                    $post_id,
                    self::medium_facebook,
                    $facebook_page,
                ];
                $next_schedule = wp_next_scheduled( self::event_publish, $args );
                if ( ! $next_schedule ) {
                    wp_schedule_single_event( $time, self::event_publish, $args );
                    Plugin::logger_instance()->info( "Scheduled Facebook page publish event" );
                }
            }

            if ( isset( $options['linkedin_account'] ) ) {
                $args          = [
                    $post_id,
                    self::medium_linkedin,
                    '',
                    self::type_user,
                ];
                $next_schedule = wp_next_scheduled( self::event_publish, $args );
                if ( ! $next_schedule ) {
                    wp_schedule_single_event( $time, self::event_publish, $args );
                    Plugin::logger_instance()->info( "Scheduled LinkedIn account publish event" );
                }
            }

            foreach ( $options['linkedin_pages'] as $linkedin_page ) {
                $args          = [
                    $post_id,
                    self::medium_linkedin,
                    $linkedin_page,
                    self::type_page,
                ];
                $next_schedule = wp_next_scheduled( self::event_publish, $args );
                if ( ! $next_schedule ) {
                    wp_schedule_single_event( $time, self::event_publish, $args );
                    Plugin::logger_instance()->info( "Scheduled LinkedIn page publish event" );
                }
            }

            if ( isset( $options['twitter_account'] ) ) {
                $args          = [
                    $post_id,
                    self::medium_twitter,
                ];
                $next_schedule = wp_next_scheduled( self::event_publish, $args );
                if ( ! $next_schedule ) {
                    wp_schedule_single_event( $time, self::event_publish, $args );
                    Plugin::logger_instance()->info( "Scheduled Twitter account publish event" );
                }
            }

            if ( isset( $options['yammer_network'] ) ) {
                $args          = [
                    $post_id,
                    self::medium_yammer,
                ];
                $next_schedule = wp_next_scheduled( self::event_publish, $args );
                if ( ! $next_schedule ) {
                    wp_schedule_single_event( $time, self::event_publish, $args );
                    Plugin::logger_instance()->info( "Scheduled Yammer network publish event" );
                }
            }

            update_post_meta( $post_id, 'jm_smap_published', 1 );
        }
    }

    public static function has_any_event_scheduled( $post_id, $options ) {
        $any = [];

        $next_schedule = wp_next_scheduled( self::event_visit, [ $post_id ] );
        if ( $next_schedule ) {
            $any[] = $next_schedule;
        }

        if ( isset( $options['facebook_account'] ) ) {
            $args          = [
                $post_id,
                self::medium_facebook,
                'me',
            ];
            $next_schedule = wp_next_scheduled( self::event_publish, $args );
            if ( $next_schedule ) {
                $any[] = $next_schedule;
            }
        }

        foreach ( $options['facebook_pages'] as $facebook_page ) {
            $args          = [
                $post_id,
                self::medium_facebook,
                $facebook_page,
            ];
            $next_schedule = wp_next_scheduled( self::event_publish, $args );
            if ( $next_schedule ) {
                $any[] = $next_schedule;
            }
        }

        if ( isset( $options['linkedin_account'] ) ) {
            $args          = [
                $post_id,
                self::medium_linkedin,
                '',
                self::type_user,
            ];
            $next_schedule = wp_next_scheduled( self::event_publish, $args );
            if ( $next_schedule ) {
                $any[] = $next_schedule;
            }
        }

        foreach ( $options['linkedin_pages'] as $linkedin_page ) {
            $args          = [
                $post_id,
                self::medium_linkedin,
                $linkedin_page,
                self::type_page,
            ];
            $next_schedule = wp_next_scheduled( self::event_publish, $args );
            if ( $next_schedule ) {
                $any[] = $next_schedule;
            }
        }

        if ( isset( $options['twitter_account'] ) ) {
            $args          = [
                $post_id,
                self::medium_twitter,
            ];
            $next_schedule = wp_next_scheduled( self::event_publish, $args );
            if ( $next_schedule ) {
                $any[] = $next_schedule;
            }
        }

        if ( isset( $options['yammer_network'] ) ) {
            $args          = [
                $post_id,
                self::medium_yammer,
            ];
            $next_schedule = wp_next_scheduled( self::event_publish, $args );
            if ( $next_schedule ) {
                $any[] = $next_schedule;
            }
        }

        return $any;
    }

    public static function publish( $post_id, $medium, $medium_id = '', $type = self::type_user ) {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post ) {
            return;
        }

        Plugin::logger_instance()->info( "Going to publish post {$post_id}" );

        $message          = get_the_title( $post );
        $title            = get_the_title( $post );
        $url              = get_permalink( $post_id );
        $image            = get_the_post_thumbnail_url( $post, 'large' );
        $link_description = get_the_excerpt( $post );

        if ( $_yoast_wpseo_opengraph_title = get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true ) ) {
            // if og title is set use it
            $title   = $_yoast_wpseo_opengraph_title;
            $message = $_yoast_wpseo_opengraph_title;
        } else if ( $_yoast_wpseo_title = get_post_meta( $post_id, '_yoast_wpseo_title', true ) ) {
            // otherwise use the seo title if set
            $title = $_yoast_wpseo_title;
        }

        if ( $_yoast_wpseo_opengraph_description = get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true ) ) {
            // if og description is set use it
            $link_description = $_yoast_wpseo_opengraph_description;
        } else if ( $_yoast_wpseo_metadesc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) ) {
            // otherwise use the seo meta desc if set
            $link_description = $_yoast_wpseo_metadesc;
        }

        if ( $_yoast_wpseo_opengraph_image = get_post_meta( $post_id, '_yoast_wpseo_opengraph-image', true ) ) {
            // if og image is set use it
            $image = $_yoast_wpseo_opengraph_image;
        }

        Plugin::logger_instance()->info( "Message: {$message}" );
        Plugin::logger_instance()->info( "Title: {$title}" );
        Plugin::logger_instance()->info( "URL: {$url}" );
        Plugin::logger_instance()->info( "Image: {$image}" );

        if ( defined( 'WP_ENV' ) && in_array( WP_ENV, [ 'development', 'staging', 'testing', ] ) ) {
            Plugin::logger_instance()->info( 'Returning because we are developing' );

            return;
        }

        Plugin::logger_instance()->info( "Medium: {$medium}" );

        switch ( $medium ) {
            case self::medium_facebook:
                Facebook::post_link( $post_id, $url, $message, $medium_id );
                break;
            case self::medium_linkedin:
                switch ( $type ) {
                    default:
                    case self::type_user:
                        LinkedIn::post_link( $post_id, $url, $message, $title, $link_description, $image );
                        break;
                    case self::type_page:
                        LinkedIn::post_page_link( $post_id, $url, $message, $medium_id, $title, $link_description, $image );
                        break;
                }
                break;
            case self::medium_twitter:
                Twitter::post_link( $post_id, $url, $message );
                break;
            case self::medium_yammer:
                Yammer::post_link( $post_id, $url, $message );
                break;
        }
    }

    public static function visit( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post ) {
            return;
        }

        Plugin::logger_instance()->info( "Going to visit post {$post_id}" );

        Plugin::logger_instance()->info( sprintf( "WP remote get '%s'", get_permalink( $post_id ) ) );
        $response = wp_remote_get( get_post_permalink( $post_id ), [
            'timeout'   => 20000,
            'sslverify' => ! JM_SMAP_DEBUG,
        ] );
        Plugin::logger_instance()->info( wp_remote_retrieve_response_code( $response ) );
        add_post_meta( $post_id, 'jmsmap_visit_response_body', wp_remote_retrieve_body( $response ) );
        Plugin::logger_instance()->info( 'Response body van de visit is opgeslagen bij de post: ' . get_admin_url( null, 'post.php?post=' . $post_id . '&action=edit' ) );
    }
}
