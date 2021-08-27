<?php

namespace Pantheon\TerminusClu\ServiceProviders\RepositoryProviders;

use Pantheon\TerminusBuildTools\API\GitLab\GitLabAPI;

trait CluGitTrait {

  public function createGitCluProvider($git_provider_class_or_alias) {
    if (!class_exists($git_provider_class_or_alias)) {
      switch ($git_provider_class_or_alias) {
        case 'bitbucket':
          $git_provider_class_or_alias = '\Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\Bitbucket\BitbucketProvider';
          break;
        case 'gitlab':
          $git_provider_class_or_alias = '\Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\GitLab\GitLabProvider';
          break;
        case 'github':
          $git_provider_class_or_alias = '\Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\GitHub\GitHubProvider';
          break;
      }
    }
    $provider = new $git_provider_class_or_alias($this->config);
    if (!$provider instanceof GitProvider) {
      throw new \Exception("Requested provider $git_provider_class_or_alias does not implement required interface Pantheon\TerminusBuildTools\ServiceProviders\RepositoryProviders\GitProvider");
    }
    $provider->setLogger($this->logger);
    $this->providerManager()->credentialManager()->add($provider->credentialRequests());
    return $provider;
  }

  /**
   * @param $url
   *
   * @return \Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\GitProvider|void
   * @throws \Exception
   */
  public function inferGitCluProviderFromUrl($url) {
    if (false !== strpos($url, 'bitbucket')) {
      return $this->createGitCluProvider('\Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\Bitbucket\BitbucketProvider');
    }

    if (false !== strpos($url, GitLabAPI::determineGitLabUrl($this->config))) {
      return $this->createGitCluProvider('\Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\GitLab\GitLabProvider');
    }

    if (false !== strpos($url, 'github')) {
      return $this->createGitCluProvider('\Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\GitHub\GitHubProvider');
    }
  }

}
