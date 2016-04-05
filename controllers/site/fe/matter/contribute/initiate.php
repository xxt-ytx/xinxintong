<?php
namespace site\fe\matter\contribute;

require_once dirname(__FILE__) . '/base.php';
/**/
class resumable {

	private $site;

	private $dest;

	private $modelFs;

	public function __construct($site, $dest, $modelFs) {

		$this->mpid = $site;

		$this->dest = $dest;

		$this->modelFs = $modelFs;
	}
	/**
	 *
	 * Logging operation
	 *
	 * @param string $str - the logging string
	 */
	private function _log($str) {
	}
	/**
	 *
	 * Delete a directory RECURSIVELY
	 *
	 * @param string $dir - directory path
	 * @link http://php.net/manual/en/function.rmdir.php
	 */
	private function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dir . "/" . $object) == "dir") {
						$this->rrmdir($dir . "/" . $object);
					} else {
						unlink($dir . "/" . $object);
					}
				}
			}
			reset($objects);
			rmdir($dir);
		}
	}
	/**
	 *
	 * Check if all the parts exist, and
	 * gather all the parts of the file together
	 *
	 * @param string $temp_dir - the temporary directory holding all the parts of the file
	 * @param string $fileName - the original file name
	 * @param string $chunkSize - each chunk size (in bytes)
	 * @param string $totalSize - original file size (in bytes)
	 */
	private function createFileFromChunks($temp_dir, $fileName, $chunkSize, $totalSize) {
		// count all the parts of this file
		$total_files = 0;
		foreach (scandir($temp_dir) as $file) {
			if (stripos($file, \TMS_MODEL::toLocalEncoding($fileName)) !== false) {
				$total_files++;
			}
		}
		// check that all the parts are present
		// the size of the last part is between chunkSize and 2*$chunkSize
		if ($total_files * $chunkSize >= ($totalSize - $chunkSize + 1)) {
			// create the final destination file
			if (($fp = fopen($this->modelFs->rootDir . $this->dest, 'w')) !== false) {
				for ($i = 1; $i <= $total_files; $i++) {
					$partname = $temp_dir . '/' . \TMS_MODEL::toLocalEncoding($fileName) . '.part' . $i;
					$content = file_get_contents($partname);
					fwrite($fp, $content);
					$this->_log('writing chunk ' . $i);
				}
				fclose($fp);
			} else {
				$this->_log('cannot create the destination file');
				return false;
			}
			// rename the temporary directory (to avoid access from other
			// concurrent chunks uploads) and than delete it
			if (rename($temp_dir, $temp_dir . '_UNUSED')) {
				$this->rrmdir($temp_dir . '_UNUSED');
			} else {
				$this->rrmdir($temp_dir);
			}
		}
	}
	/**
	 * 处理分段上传的请求
	 */
	public function handleRequest() {
		$filename = str_replace(' ', '_', $_POST['resumableFilename']);
		foreach ($_FILES as $file) {
			// check the error status
			if ($file['error'] != 0) {
				$this->_log('error ' . $file['error'] . ' in file ' . $filename);
				continue;
			}
			// init the destination file (format <filename.ext>.part<#chunk>
			// the file is stored in a temporary directory
			$tmpDir = $_POST['resumableIdentifier'];
			$tmpFile = $filename . '.part' . $_POST['resumableChunkNumber'];
			if (!$this->modelFs->upload($file['tmp_name'], $tmpFile, $tmpDir)) {
				$this->_log('Error saving chunk ' . $_POST['resumableChunkNumber'] . ' for file ' . $filename);
			} else {
				// check if all the parts present, and create the final destination file
				$absTmpDir = $this->modelFs->rootDir . '/' . $tmpDir;
				$this->createFileFromChunks($absTmpDir, $filename, $_POST['resumableChunkSize'], $_POST['resumableTotalSize']);
			}
		}
	}
}
/**
 * 发起投稿
 */
class initiate extends base {
	/**
	 * 获得当前用户的信息
	 * $site
	 * $entry
	 */
	public function index_action() {
		\TPL::output('/site/fe/matter/contribute/initiate/list');
		exit;
	}
	/**
	 * 单篇文稿页面
	 */
	public function article_action($site, $id) {
		$article = $this->getArticle($site, $id);

		$disposer = $article->disposer;
		if ($disposer) {
			$member = $this->model('site\user\member')->byId($disposer->mid);
			if ($member->userid === $this->who->uid && $disposer->phase === 'I' && $disposer->state === 'P') {
				$this->model()->update(
					'xxt_article_review_log',
					array('read_at' => time(), 'state' => 'D'),
					"id=$disposer->id");
			}
		}
		/**
		 * 只有为投稿状态，且在PC端打开的时候才允许编辑
		 */
		if ($article->approved === 'N' && (empty($article->disposer) || $article->disposer->phase === 'I')) {
			\TPL::output('/site/fe/matter/contribute/initiate/article');
		} else {
			\TPL::output('/site/fe/matter/contribute/initiate/article-r');
		}
		exit;
	}
	/**
	 * 单篇文稿页面
	 */
	public function reviewlog_action($site, $id) {
		$article = $this->getArticle($site, $id);

		$disposer = $article->disposer;
		if ($disposer) {
			$member = $this->model('site\user\member')->byId($disposer->mid);
			if ($member->userid === $this->who->uid && $disposer->phase === 'I' && $disposer->state === 'P') {
				$this->model()->update(
					'xxt_article_review_log',
					array('read_at' => time(), 'state' => 'D'),
					"id=$disposer->id");
			}
		}

		list($entryType, $entryId) = explode(',', $article->entry);
		$initiators = $this->model('app\contribute')->userAcls($site, $entryId, 'I'); // todo ???

		$params = array();
		$params['fid'] = $this->user->fid;
		$params['needReview'] = empty($initiators) ? 'N' : 'Y';

		\TPL::assign('params', $params);

		if (empty($article->disposer) || $article->disposer->phase === 'I') {
			$this->view_action('/app/contribute/initiate/article');
		} else {
			$this->view_action('/app/contribute/initiate/article-r');
		}
	}
	/**
	 * 当前用户文稿
	 */
	public function articleList_action($site, $entry, $openid = null) {
		$articleModel = $this->model('matter\article2');
		$myArticles = $articleModel->byEntry($site, $entry, $this->who->uid, '*');
		if (!empty($myArticles)) {
			foreach ($myArticles as &$a) {
				$a->disposer = $articleModel->disposer($a->id);
				$disposer = $a->disposer;
				if (!empty($disposer)) {
					$member = $this->model('site\user\member')->byId($disposer->mid);
					if ($member->userid === $this->who->uid && $disposer->phase === 'I' && $disposer->receive_at == 0) {
						$this->model()->update(
							'xxt_article_review_log',
							array('receive_at' => time()),
							"id=" . $a->disposer->id);
					}
				}
			}
		}

		return new \ResponseData($myArticles);
	}
	/**
	 * 新建一个文稿
	 *
	 * @param string $site
	 * @param string $entry
	 */
	public function articleCreate_action($site, $entry) {
		$site = $this->model('site')->byId($site, 'id,heading_pic');
		$current = time();
		$user = $this->who;

		$article = array();
		$article['siteid'] = $site->id;
		$article['entry'] = $entry;
		$article['creater'] = $user->uid;
		$article['creater_name'] = $user->nickname;
		$article['creater_src'] = 'U';
		$article['create_at'] = $current;
		$article['modifier'] = $user->uid;
		$article['modifier_name'] = $user->nickname;
		$article['modifier_src'] = 'U';
		$article['modify_at'] = $current;
		$article['title'] = '新文稿';
		$article['pic'] = $site->heading_pic;
		$article['hide_pic'] = 'N';
		$article['summary'] = '';
		$article['url'] = '';
		$article['weight'] = 70;
		$article['body'] = '';
		$article['finished'] = 'N';
		$article['approved'] = 'N';
		$article['public_visible'] = 'Y';
		$article['remark_notice'] = 'Y';

		$id = $this->model()->insert('xxt_article', $article, true);
		/**
		 * 设置频道
		 */
		list($entryType, $entryId) = explode(',', $entry);
		$entry = $this->model('matter\\' . $entryType)->byId($entryId, 'params');
		$params = json_decode($entry->params);
		if (!empty($params->channel)) {
			$channelId = $params->channel;
			$this->model('matter\channel')->addMatter($channelId, array('id' => $id, 'type' => 'article'), $user->uid, $user->nickname, 'M');
		}

		$article = $this->model('matter\article2')->byId($id);

		return new \ResponseData($article);
	}
	/**
	 * 将文件生成的图片转为正文
	 */
	private function setBodyByAtt($articleid, $dir) {
		$body = '';
		$files = scandir($dir);
		$dir = \TMS_MODEL::toUTF8($dir);
		for ($i = 0, $l = count($files) - 2; $i < $l; $i++) {
			$body .= '<p>';
			$body .= '<img src="' . '/' . $dir . '/' . $i . '.jpg">';
			$body .= '</p>';
		}

		$rst = $this->model()->update(
			'xxt_article',
			array('body' => $body),
			"id='$articleid'"
		);

		return $body;
	}
	/**
	 * 将文件生成的图片的第一张设置为头图
	 */
	private function setCoverByAtt($articleid, $dir) {
		$dir = \TMS_MODEL::toUTF8($dir);
		$url = '/' . $dir . '/0.jpg';
		$rst = $this->model()->update(
			'xxt_article',
			array('pic' => $url),
			"id='$articleid'"
		);

		return $url;
	}
	/**
	 * 上传文件并创建图文
	 */
	public function articleUpload_action($site, $entry = null, $state = null) {
		if ($state === 'done') {
			$fan = $this->model('user/fans')->byId($this->user->fid, 'nickname');

			$posted = $this->getPostJson();
			$file = $posted->file;

			$modelFs = \TMS_APP::M('fs/local', $site, '_resumable');
			$fileUploaded = $modelFs->rootDir . '/article_' . $file->uniqueIdentifier;

			$current = time();
			$filename = str_replace(' ', '_', $file->name);

			$d = array();
			$d['mpid'] = $site;
			$d['entry'] = $entry;
			$d['creater'] = $this->user->mid;
			$d['creater_name'] = $fan->nickname;
			$d['creater_src'] = 'M';
			$d['create_at'] = $current;
			$d['modifier'] = $this->user->mid;
			$d['modifier_src'] = 'M';
			$d['modifier_name'] = $fan->nickname;
			$d['modify_at'] = $current;
			$d['title'] = substr($filename, 0, strrpos($filename, '.'));
			$d['author'] = $fan->nickname;
			$d['url'] = '';
			$d['hide_pic'] = 'Y';
			$d['can_picviewer'] = 'Y';
			$d['has_attachment'] = 'Y';
			$d['pic'] = '';
			$d['summary'] = '';
			$d['body'] = '';
			$d['finished'] = 'N';
			$d['approved'] = 'N';
			$d['public_visible'] = 'Y';
			$d['remark_notice'] = 'Y';

			$id = $this->model()->insert('xxt_article', $d, true);
			/**
			 * 设置频道
			 */
			list($entryType, $entryId) = explode(',', $entry);
			$entry = $this->model('matter\\' . $entryType)->byId($entryId, 'params');
			$params = json_decode($entry->params);
			if (!empty($params->channel)) {
				$channelId = $params->channel;
				$this->model('matter\channel')->addMatter($channelId, array('id' => $id, 'type' => 'article'), $this->user->mid, $fan->nickname, 'M');
			}
			/**
			 * 保存附件
			 */
			$att = array();
			$att['article_id'] = $id;
			$att['name'] = $filename;
			$att['type'] = $file->type;
			$att['size'] = $file->size;
			$att['last_modified'] = $file->lastModified;
			$att['url'] = 'local://article_' . $id . '_' . $filename;

			$this->model()->insert('xxt_article_attachment', $att, true);

			$modelFs = \TMS_APP::M('fs/local', $site, '附件');
			$attachment = $modelFs->rootDir . '/article_' . $id . '_' . \TMS_MODEL::toLocalEncoding($filename);
			rename($fileUploaded, $attachment);
			/**
			 * 获取附件的内容
			 */
			$appRoot = $_SERVER['DOCUMENT_ROOT'];
			$ext = explode('.', $filename);
			$ext = array_pop($ext);
			$attAbs = $appRoot . '/' . $attachment;
			if (in_array($ext, array('doc', 'docx', 'ppt', 'pptx'))) {
				/* 存放附件转换结果 */
				$attDir = str_replace('.' . $ext, '', $attachment);
				mkdir($appRoot . '/' . $attDir);
				/* 执行转换操作 */
				$output = array();
				$attPng = $appRoot . '/' . $attDir . '/%d.jpg';
				$cmd = $appRoot . '/cus/conv2pdf2img ' . $attAbs . ' ' . $attPng;
				$rsp = exec($cmd, $output, $status);
				if ($status == 1) {
					return new \ResponseError('转换文件失败：' . $rsp);
				}
				$this->setBodyByAtt($id, $attDir);
				if (in_array($ext, array('ppt', 'pptx'))) {
					$this->setCoverByAtt($id, $attDir);
				}
			} else if ($ext === 'pdf') {
				/* 存放附件转换结果 */
				$attDir = str_replace('.' . $ext, '', $attachment);
				mkdir($appRoot . '/' . $attDir);
				$attPng = $appRoot . '/' . $attDir . '/%d.jpg';
				$cmd = $appRoot . '/cus/conv2img ' . $attAbs . ' ' . $attPng;
				$rsp = exec($cmd);
				$this->setBodyByAtt($id, $attDir);
			}

			return new \ResponseData($id);
		} else {
			/**
			 * 分块上传文件
			 */
			$modelFs = \TMS_APP::M('fs/local', $site, '_resumable');
			$dest = '/article_' . $_POST['resumableIdentifier'];
			$resumable = new resumable($site, $dest, $modelFs);
			$resumable->handleRequest();
			exit;
		}
	}
	/**
	 * 删除一个文稿
	 */
	public function articleRemove_action($site, $id) {
		$rst = $this->model()->update(
			'xxt_article',
			array('state' => 0, 'modify_at' => time()),
			"creater='" . $this->who->uid . "' and id='$id'");

		return new \ResponseData($rst);
	}
	/**
	 * 转发给指定人进行处理
	 *
	 * @param string $site 公众平台ID
	 * @param int $id 文章ID
	 * @param string $phase 处理的阶段
	 * @param string $mid 审核人ID
	 */
	public function articleForward_action($site, $id, $phase, $mid) {
		$article = $this->getArticle($site, $id);
		if ($article->finished === 'N') {
			/*完成编辑并提交审核*/
			$this->model()->update(
				'xxt_article',
				array('finished' => 'Y'),
				"mpid='$site' and id='$id'"
			);
			/*奖励投稿人*/
			//$contributor = $this->getUser($site);
			//$modelCoin = $this->model('coin\log');
			//$action = 'app.' . $article->entry . '.article.submit';
			//$modelCoin->income($site, $action, $id, 'sys', $contributor->openid);
		}

		return parent::articleForward_action($site, $id, $phase, $mid);
	}
}