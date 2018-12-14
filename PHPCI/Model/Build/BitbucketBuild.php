<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Model\Build;


/**
* BitBucket Build Model
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Core
*/
class BitbucketBuild extends RemoteGitBuild
{
    /**
    * Get link to commit from another source (i.e. Github)
    */
    public function getCommitLink()
    {
        return 'https://bitbucket.org/' . $this->getProject()->getReference() . '/commits/' . $this->getCommitId();
    }

    /**
    * Get link to branch from another source (i.e. Github)
    */
    public function getBranchLink()
    {
        return 'https://bitbucket.org/' . $this->getProject()->getReference() . '/src/?at=' . $this->getBranch();
    }

    /**
    * Get the URL to be used to clone this remote repository.
    */
    protected function getCloneUrl()
    {
        $key = trim($this->getProject()->getSshPrivateKey());

        if (!empty($key)) {
            return 'git@bitbucket.org:' . $this->getProject()->getReference() . '.git';
        } else {
            return 'https://bitbucket.org/' . $this->getProject()->getReference() . '.git';
        }
    }

    /**
     * @return void
     */
    public function sendStatusPostback()
    {
        $userpwd = \b8\Config::getInstance()->get("phpci.bitbucket.userpwd");
        if (empty($userpwd) || empty($this->data['id']) || empty($this->data['commit_id'])) {
            return;
        }

        $preferences = $this->getProject()->getReference();
        $preferences = explode("/", $preferences);

        if (!isset($preferences[0]) || !isset($preferences[1])) {
            return;
        }

        $username = $preferences[0];
        $repoSlug = $preferences[1];

        $baseUrl = \b8\Config::getInstance()->get("phpci.url");
        $data = array (
            'state' => '',
            'key' => $this->getBranch() . "-" .  $this->data['id'],
            'name' => $this->getBranch() . "-" .  $this->data['id'],
            'url' => "{$baseUrl}/build/view/{$this->data['id']}",
            'description' => ""
        );



        // https://api.bitbucket.org/2.0/repositories/<user-name>/<repo-name>/commit/<hash>/statuses/build"
        $url = "https://api.bitbucket.org/2.0/repositories/{$username}/{$repoSlug}/commit/{$this->data['commit_id']}/statuses/build";
        switch ($this->getStatus()) {
            case 0:
            case 1:
                $data['state'] = 'INPROGRESS';
                $data['description'] = 'In progress';
                break;
            case 2:
                $data['state'] = 'SUCCESSFUL';
                $data['description'] = 'Successful';
                break;
            case 3:
            default:
                $data['state'] = 'FAILED';
                $data['description'] = 'Failed';
                break;
        }

        $payload = json_encode($data);
        $headers = array(
            'Content-Type: application/json',
        );

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_USERPWD, $userpwd);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        curl_exec($curl);
        curl_close($curl);
    }
}
