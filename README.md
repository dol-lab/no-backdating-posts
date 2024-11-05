# No Backdating Posts

A WordPress plugin that prevents backdating of posts and pages. 
This helps maintain content integrity by ensuring published dates can't be modified to dates earlier than the original publication date.

## Features

- Prevents backdating of posts and pages by default
- Configurable grace period from now (default: 1 hour). So you can set the time of the post to 8:00 when the current time is 9:00.
- Support for Gutenberg
- Warning notifications when backdating is attempted. The date is automatically set to the "earliest allowed".
- Capability-based permissions


## Configuration

### Capabilities

- `backdate_posts` - Allow backdating for all post types
- `backdate_{post_type}` - Allow backdating for specific post type (e.g., `backdate_page`)

### Filters

- `no_backdating_post_types` - Array of post types to prevent backdating (default: `['post', 'page']`)
```php
add_filter('no_backdating_post_types', function($post_types) {
    return ['post', 'page', 'custom_post_type'];
});
```

- `no_backdating_grace_period` - Grace period in seconds (default: 1 hour)
```php
add_filter('no_backdating_grace_period', function($grace_period) {
    return 2 * HOUR_IN_SECONDS; // 2 hours
});
```

## License

GPL v3 or later - [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
