pipeline {
  agent none
  stages {
    stage('codestyles') {
      agent {
        docker 'rdeutz/docker-phpcs'
      }
      steps {
        sh 'echo $(date)'
        //sh '/usr/local/vendor/bin/phpcs --report=full --extensions=php -p --standard=build/phpcs/Joomla .'
      }
    }
    stage('Testing-PHP5') {
      agent {
        docker 'rdeutz/docker-php56'
      }
      steps {
        sh 'echo $(date)'
        //sh 'phpunit'
      }
    }
    stage('Testing-Javascript') {
      agent {
        docker 'joomlaprojects/docker-systemtests'
      }
      steps {
        sh 'echo $(date)'
        sh 'apt-get install nodejs npm'
        sh 'ln -s /usr/bin/nodejs /usr/bin/node'
        sh 'export DISPLAY=:0'
        sh 'Xvfb -screen 0 1024x768x24 -ac +extension GLX +render -noreset > /dev/null 2>&1 &'
        sh 'sleep 3'
        sh 'fluxbox  > /dev/null 2>&1 &'
        sh 'cd tests/javascript'
        sh 'npm install'
        sh 'cd ../..'
        sh 'tests/javascript/node_modules/karma/bin/karma start karma.conf.js --single-run'
        sh 'echo $(date)'
      }
    }
  }
}
