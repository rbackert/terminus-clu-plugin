<?php

namespace Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\GitHub;

use Pantheon\TerminusBuildTools\ServiceProviders\RepositoryProviders\GitHub\GitHubProvider as BuildToolsGitHubProvider;
use Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\GitProvider;

class GitHubProvider extends BuildToolsGitHubProvider implements GitProvider {

  public function cloneRepository($target_project, $destination) {
    $github_token = $this->token();
    $remote_url = "https://${github_token}:x-oauth-basic@github.com/${target_project}.git";
    $this->execWithRedaction("git clone {remote} $destination", ['remote' => $remote_url], ['remote' => $target_project]);
  }

  public function closePullRequest($target_project, $id) {
    $this->logger
      ->notice("Closing PR {id} on {project}", [
        'id' => $id,
        'project' => $target_project,
      ]);

    if ($data = $this->api()
      ->request("repos/$target_project/pulls/$id", [
        'state' => 'closed',
      ], 'PATCH')) {
      $this->logger->notice("Pull request {id} has been closed.", ["id" => $id]);
      return $data;
    }
    $this->logger->error("Failed to close pull request {id}.", ["id" => $id]);
  }

  private function getProjectDetails($target_project) {
    if ($data = $this->api()
      ->request("repos/$target_project", [], 'GET')) {
      return $data;
    }
    $this->logger->error("{project} is an invalid project.", ["project" => $target_project]);
  }

  public function createPullRequest($target_project, $source_branch, $title, array $options = []) {
    $postData = [
      'title' => $title,
      'head' => $source_branch,
    ];

    if (!empty($options['target'])) {
      $postData['base'] = $options['target'];
      unset($options['target']);
    } else {
      $project_details = $this->getProjectDetails($target_project);
      $postData['base'] = $project_details['default_branch'];
    }

    if(!empty($options['draft'])) {
      $postData['draft'] = boolval($options['draft']);
    }

    // GitHub doesn't have reviewers on pull request creation
    // See https://developer.github.com/v3/pulls/#create-a-pull-request
    unset($options['reviewers']);

    // GitHub doesn't have delete source branch on pull request creation
    // See https://developer.github.com/v3/pulls/#create-a-pull-request
    unset($options['close']);

    $postData += array_filter($options);

    $this->logger
      ->notice("Creating pull request on {project} for {source}", [
        "project" => $target_project,
        "source" => $source_branch,
      ]);
    if ($data =
      $this->api()
        ->request("repos/$target_project/pulls", $postData, 'POST')) {
      $this->logger
        ->notice("Pull request #{id} \"{title}\" created successfully: {url}", [
          "id" => $data['id'],
          "title" => $data['title'],
          "url" => $data['html_url']
        ]);
      return $data;
    }
    $this->logger
      ->error("Creating pull request failed");
    return FALSE;
  }

}