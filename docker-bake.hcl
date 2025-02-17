variable "VERSION" {
  default = "source"
}

variable "VERSION_URL" {
  default = ""
}

variable "VERSION_DATE" {
  default = ""
}

variable "GITHUB_REF_NAME" {
  default = ""
}

target "fpm" {
  context = "backend"
  args = {
    VERSION = "${VERSION}"
    VERSION_URL = "${VERSION_URL}"
    VERSION_DATE = "${VERSION_DATE}"
  }
  labels = {
    "org.opencontainers.image.description" = "Pilcrow FPM Container Image version: ${ VERSION }@${VERSION_DATE } (${ VERSION_URL })"
  }
  output = ["type=image,push=true,annotation-index.org.opencontainers.image.description=Pilcrow FPM Container Image version: ${ VERSION }@${VERSION_DATE } (${ VERSION_URL })"]
  cache_from = ["type=registry,ref=ghcr.io/mesh-research/pilcrow/cache/fpm-${VERSION}:cache"]
  cache_to = ["type=registry,ref=ghcr.io/mesh-research/pilcrow/cache/fpm-${VERSION}:cache"]

}


target "web" {
  context = "client"
  args = {
    VERSION = "${VERSION}"
    VERSION_URL = "${VERSION_URL}"
    VERSION_DATE = "${VERSION_DATE}"
  }
  labels = {
    "org.opencontainers.image.description" = "Pilcrow WEB Container Image version: ${ VERSION }@${VERSION_DATE } (${ VERSION_URL })"
  }
  output = ["type=image,push=true,annotation-index.org.opencontainers.image.description=Pilcrow WEB Container Image version: ${ VERSION }@${VERSION_DATE } (${ VERSION_URL })"]
  cache_from = ["type=registry,ref=ghcr.io/mesh-research/pilcrow/cache/web-${VERSION}:cache"]
  cache_to = ["type=registry,ref=ghcr.io/mesh-research/pilcrow/cache/web-${VERSION}:cache"]

}


target "fpm-release" {
  inherits = ["fpm"]
}

target "web-release" {
  inherits = ["web"]
  platforms = ["linux/amd64", "linux/arm64"]
}

group "default" {
  targets = ["fpm", "web"]
}


group "release" {
  targets = ["fpm-release", "web-release"]

}
