<?php

/**
 * Welcome to the inside of Wikibase,              <>
 * the software that powers                   /\        /\
 * Wikidata and other                       <{  }>    <{  }>
 * structured data websites.        <>   /\   \/   /\   \/   /\   <>
 *                                     //  \\    //  \\    //  \\
 * It is Free Software.              <{{    }}><{{    }}><{{    }}>
 *                                /\   \\  //    \\  //    \\  //   /\
 *                              <{  }>   ><        \/        ><   <{  }>
 *                                \/   //  \\              //  \\   \/
 *                            <>     <{{    }}>     +--------------------------+
 *                                /\   \\  //       |                          |
 *                              <{  }>   ><        /|  W  I  K  I  B  A  S  E  |
 *                                \/   //  \\    // |                          |
 * We are                            <{{    }}><{{  +--------------------------+
 * looking for people                  \\  //    \\  //    \\  //
 * like you to join us in           <>   \/   /\   \/   /\   \/   <>
 * developing it further. Find              <{  }>    <{  }>
 * out more at http://wikiba.se               \/        \/
 * and join the open data revolution.              <>
 */

/**
 * Testing entry point. Do not use for production setups!
 *
 * @see README.md
 * @see http://wikiba.se
 * @licence GNU GPL v2+
 */

//TODO: Use a different file for jenkins, use this for a standard repo+client setup.

if( isset( $wgWikimediaJenkinsCI ) && !$wgWikimediaJenkinsCI ) {
	die( "This entry point is for use by the Jenkins testing framework only.\n"
		. "Use repo/Wikibase.php resp. client/WikibaseClient.php instead.\n" );
}

// Avoid polluting the global namespace
unset( $jenkins_job_name );