import { glob } from 'astro/loaders';
import { defineCollection, z } from 'astro:content';
import { removeDupsAndLowerCase } from './utils/functions';

const blog = defineCollection({
	// Load Markdown and MDX files in the `src/content/blog/` directory.
	loader: glob({ base: './src/content/blog', pattern: '**/*.{md,mdx}' }),
	// Type-check frontmatter using a schema
	schema: z.object({
		title: z.string(),
		description: z.string(),
		keywords:z.array(z.string()).default([]).transform(removeDupsAndLowerCase),
    // Transform string to Date object
		pubDate: z.coerce.date(),
		updatedDate: z.coerce.date().optional(),
		heroImage: z.string().optional(),
    heroImageAlt: z.string().optional(),
	}),
});

export const collections = { blog };
