<?php
namespace site\fe\matter;

include_once dirname(dirname(__FILE__)) . '/base.php';
/**
 *
 */
class base extends \site\fe\base {
	/**
	 * 跳转到用户认证页
	 */
	protected function gotoMember($siteId, $aMemberSchemas, $userid, $targetUrl = null) {
		is_string($aMemberSchemas) && $aMemberSchemas = explode(',', $aMemberSchemas);
		/**
		 * 如果不是注册用户，要求先进行认证
		 */
		if (count($aMemberSchemas) === 1) {
			$schema = $this->model('site\user\memberschema')->byId($aMemberSchemas[0], 'id,url');
			strpos($schema->url, 'http') === false && $authUrl = 'http://' . $_SERVER['HTTP_HOST'];
			$authUrl .= $schema->url;
			$authUrl .= "?site=$siteId";
			!empty($userid) && $authUrl .= "&userid=$userid";
			$authUrl .= "&schema=" . $aMemberSchemas[0];
		} else {
			/**
			 * 让用户选择通过那个认证接口进行认证
			 */
			$authUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/rest/site/user/member/authoptions';
			$authUrl .= "?mpid=$siteId";
			!empty($userid) && $authUrl .= "&userid=$userid";
			$authUrl .= "&schema=" . implode(',', $aMemberSchemas);
		}
		/**
		 * 返回身份认证页
		 */
		if ($targetUrl === false) {
			/**
			 * 直接返回认证地址
			 * todo angular无法自动执行初始化，所以只能返回URL，由前端加载页面
			 */
			$protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
			header($protocol . ' 401 Unauthorized');
			die("$authUrl");
		} else {
			/**
			 * 跳转到认证接口
			 */
			if (empty($targetUrl)) {
				$targetUrl = $this->getRequestUrl();
			}
			/**
			 * 将跳转信息保存在cookie中
			 */
			$targetUrl = $this->model()->encrypt($targetUrl, 'ENCODE', $siteId);
			$this->mySetCookie("_{$siteId}_mauth_t", $targetUrl, time() + 300);
			$this->redirect($authUrl);
		}
	}
	/**
	 * 访问控制设置
	 *
	 * 检查当前用户是否为认证用户
	 * 检查当前用户是否在白名单中
	 *
	 * 如果用户没有认证，跳转到认证页
	 *
	 */
	protected function accessControl($siteId, $objId, $memberSchemas, $userid, &$obj, $targetUrl = null) {
		$aMemberSchemas = explode(',', $memberSchemas);
		$members = $this->model('site\user\member')->byUser($siteId, $userid);
		if (empty($members)) {
			/**
			 * 如果不是认证用户，先进行认证
			 */
			$this->gotoMember($siteId, $aMemberSchemas, $userid, $targetUrl);
		} else {
			$model = $this->model();
			$passed = false;
			foreach ($members as $member) {
				if ($this->canAccessObj($siteId, $objId, $member, $memberSchemas, $obj)) {
					/**
					 * 检查用户是否通过了验证
					 */
					$q = array(
						'verified',
						'xxt_site_member',
						"siteid='$siteId' and id='$member->id'",
					);
					if ('Y' !== $model->query_val_ss($q)) {
						$r = $this->model('site\user\memberschema')->getNotpassStatement($member->schema_id, $siteId);
						$protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
						header($protocol . ' 401 Unauthorized');
						\TPL::assign('title', '访问控制未通过');
						\TPL::assign('body', $r);
						\TPL::output('error');
						exit;
					}
					$passed = true;
					break;
				}
			}
			!$passed && $this->gotoOutAcl($siteId, $member->schema_id);

			return $member;
		}
	}
}