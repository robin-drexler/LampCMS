<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */



namespace Lampcms\Modules\Tumblr;

use \Lampcms\Points;

/**
 * Class for posting Questions ans Answers
 * to Tumblr via Oauth API
 *
 *
 * @author Dmitri Snytkine
 *
 */
class PostTumblr extends \Lampcms\Observer
{
	/**
	 * TUMBLR API Config array
	 * a TUMBLR section from !config.ini
	 *
	 * @var array
	 */
	protected $aConfig;

	public function main(){

		$a = $this->oRegistry->Request->getArray();
		if(empty($a['tumblr'])){
			d('tumblr checkbox not checked');
			/**
			 * Set the preference in Viewer object
			 * for that "Post to tumblr" checkbox to be not checked
			 * This is just in case it was checked before
			 */
			$this->oRegistry->Viewer['b_tm'] = false;

			return;
		}

		/**
		 * First if this site does not have support for Tumblr API
		 * OR if User does not have Tumblr credentials then
		 * there is nothing to do here
		 * This is unlikely because user without Tumblr credentials
		 * will not get to see the checkbox to post to Tumblr
		 * but still it's better to check just to be sure
		 */
		if(!extension_loaded('curl')){
			d('curl extension not present, exiting');
				
			return;
		}

		try{

			$this->aConfig = $this->oRegistry->Ini->getSection('TUMBLR');

			if(empty($this->aConfig)
			|| empty($this->aConfig['OAUTH_KEY'])
			|| empty($this->aConfig['OAUTH_SECRET'])){
				d('Tumblr API not enabled on this site');

				return;;
			}
		} catch (\Lampcms\IniException $e){
			d('Ini Exception: '.$e->getMessage());

			return;
		}

		if(null === $this->oRegistry->Viewer->getTumblrToken()){
			d('User does not have Tumblr token');
			return;
		}

		/**
		 * Now we know that user checked that checkbox
		 * to post content to Tumblr
		 * and we now going to save this preference
		 * in User object
		 *
		 */
		$this->oRegistry->Viewer['b_tm'] = true;
		d('cp');
		switch($this->eventName){
			case 'onNewQuestion':
			case 'onNewAnswer':
				$this->post();
				break;
		}
	}


	/**
	 * Post to Tumblr blog
	 *
	 */
	protected function post(){

		try{
			
			$oTumblr = new ApiClient($this->oRegistry);
			$User = $this->oRegistry->Viewer;
			if(false === $oTumblr->setUser($User)){
				d('User does not have Tumblr Oauth credentials');

				return;
			}
				
			$reward = Points::SHARED_CONTENT;
			$oResource = $this->obj;
			$oAdapter = new TumblrPostAdapter($this->oRegistry);

		} catch (\Exception $e){
			d('Unable to post to Tumblr because of this exception: '.$e->getMessage().' in file: '.$e->getFile().' on line: '.$e->getLine());
			return;
		}

		$func = function() use ($oTumblr, $oAdapter, $oResource, $User, $reward){

			$result = null;

			try{
				$result = $oTumblr->add($oAdapter->get($oResource));
			} catch(\Exception $e){
				d('Caught exception during Tumblr API post '.$e->getMessage().' in '.$e->getFile().' on line '.$e->getLine());

				return;
			}

			if(!empty($result) && is_numeric($result) ){
				d('Got resoult from Tumblr: '.$result);
				$User->setReputation($reward);
				
				/**
				 * Also save Tumblr status id to QUESTIONS or ANSWERS
				 * collection.
				 * This way later on (maybe way later...)
				 * We can add a function so that if user edits
				 * Post on the site we can also edit it
				 * on Tumblr via API
				 * Can also delete from Tumblr if Resource
				 * id deleted
				 *
				 */
				$oResource['i_tumblr'] = (int)$result;
				$oResource->save();
				d('Updated Resource with Tumblr resource id');

			}
		};

		\Lampcms\runLater($func);
	}
}
