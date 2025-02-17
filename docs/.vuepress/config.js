const { description } = require("../package");

module.exports = {
    title: "Pilcrow",
    head: [
        ["meta", { name: "theme-color", content: "#3eaf7c" }],
        ["meta", { name: "apple-mobile-web-app-capable", content: "yes" }],
        [
            "meta",
            { name: "apple-mobile-web-app-status-bar-style", content: "black" }
        ]
    ],
    base: process.env.BASE_URL || "/",
    themeConfig: {
        repo: "https://github.com/MESH-Research/Pilcrow",
        branch: process.env.BRANCH,
        editLinks: true,
        docsDir: "docs",
        docsBranch: process.env.HEAD || "master",
        editLinkText: "",
        lastUpdated: true,
        nav: [
            {
                text: "Guide",
                link: "/guide/"
            },
            {
                text: "Installation",
                link: "/install/"
            },
            {
                text: "Contributing",
                link: "/contributing/"
            }
        ],
        sidebar: {
            "/guide/": [
                {
                    title: "Guide",
                    collapsable: false
                }
            ],
            "/install/": [
                {
                    title: "Installation",
                    collapsable: false
                }
            ],
            "/contributing/": [
                {
                    title: "Contributing",
                    sidebarDepth: 2,
                    children: ["", "architecture", "testing", "copyediting", "documentation", "wsl"]
                }
            ]
        }
    },

    /**
     * Apply plugins，ref：https://v1.vuepress.vuejs.org/zh/plugin/
     */
    plugins: [
        "@vuepress/plugin-back-to-top",
        [
            "vuepress-plugin-medium-zoom",
            {
                selector: ".theme-default-content :not(a):not(figure) img",
            }
        ],
        [
            "robots",
            {
                host: "https://pilcrow.meshresearch.dev",
                disallowAll: true
            }
        ]
    ]
};
