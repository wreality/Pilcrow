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
    labels = {
        for k, v in target.default-labels.labels : k => replace(v, "__service__", "FPM")
    }
}


target "web" {
    context = "client"
    labels = {
        for k, v in target.default-labels.labels : k => replace(v, "__service__", "web")
    }
}

target "docker-metadata-action" {}

target "docker-build-cache-config-action" {}

target "default-labels" {
    labels = {
        "org.opencontainers.image.description" = "Pilcrow __service__ version: ${ VERSION }@${VERSION_DATE } (${ VERSION_URL })"
    }
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

target "ci" {

    matrix = {
        item = [
        {
            tgt = "fpm",
            cache-from = [ for v in target.docker-build-cache-config-action.cache-from : replace(v, "__service__", "fpm")]
            cache-to = [ for v in target.docker-build-cache-config-action.cache-to : replace(v, "__service__", "fpm")]
            tags = [ for v in target.docker-metadata-action.tags : replace(v, "__service__", "fpm")]
            output = [{
                "type" = "image",
                "push" = true,
                }]
        },
        {
            tgt = "web",
            cache-from = [ for v in target.docker-build-cache-config-action.cache-from : replace(v, "__service__", "web")]
            cache-to = [ for v in target.docker-build-cache-config-action.cache-to : replace(v, "__service__", "web")]
            tags = [ for v in target.docker-metadata-action.tags : replace(v, "__service__", "web")]
            output = [{
                "type" = "image",
                "push" = true,
                }, {
                "type" = "local",
                "dest" = "/tmp/webbuild"
                }]
        }
    ]
    }
    name = "ci-${item.tgt}"
    inherits = [item.tgt]
    cache-from = item.cache-from
    cache-to = item.cache-to
    tags = item.tags
    platforms = item.platforms
    output = item.output
}


target "release" {
    inherits = [ "ci" ]
    name = "release-${item.tgt}"
    matrix = {
        item = [
            target.ci.matrix.item[0],
            concat(target.ci.matrix.item[1], {
                platforms = ["linux/amd64", "linux/arm64"]
            })
        ]
    }

}
