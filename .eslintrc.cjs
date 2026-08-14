module.exports = {
    root: true,
    env: {
        browser: true,
        es2021: true,
        node: true,
    },
    extends: ['eslint:recommended', 'plugin:vue/vue3-recommended'],
    parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
    },
    rules: {
        // Inertia page components under resources/js/Pages are named after
        // routes/pages (Welcome, Dashboard, ...), not always multi-word —
        // matching the convention Laravel's own Breeze starter kit uses.
        'vue/multi-word-component-names': 'off',
    },
};
