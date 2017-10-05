pipeline {
  agent any
  stages {
    stage('build') {
      steps {
        sh '''#!/bin/bash
echo "Hello world"'''
      }
    }
    stage('') {
      steps {
        build 'build'
      }
    }
  }
}