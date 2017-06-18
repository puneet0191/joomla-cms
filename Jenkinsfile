#!groovy

pipeline {
  agent any
  stages {
    stage('codestyles') {
      agent {
        docker 'rdeutz/docker-phpcs'
      }
      steps {
        sh 'echo $(date)'
        sh '/usr/local/vendor/bin/phpcs --report=full --extensions=php -p --standard=build/phpcs/Joomla .'
      }
    }


    stage("Testing PHP") {
      steps {
        // You can only use the parallel step if it's the *only* step in the stage.
        parallel(
          PHP53: {
            echo "Testing PHP53"
          },
          PHP54: {
            echo "Testing PHP54"
          }
        )
      }
    }

    stage('Testing-Javascript') {
      agent {
        docker {
            image 'joomlaprojects/docker-systemtests'
            args  '--user 0'
        }
      }
      steps {
        sh 'echo $(date)'
        sh '''
            ln -s /usr/bin/nodejs /usr/bin/node && \
            export DISPLAY=:0 && \
            (Xvfb -screen 0 1024x768x24 -ac +extension GLX +render -noreset &) && \
            sleep 3 && \
            (fluxbox &) && \
            cd tests/javascript && npm install --no-optional && cd ../.. && \
            tests/javascript/node_modules/karma/bin/karma start karma.conf.js --single-run
        '''
        sh 'echo $(date)'
      }
    }
  }
}

