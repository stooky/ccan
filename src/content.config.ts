import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

const blog = defineCollection({
  loader: glob({ pattern: "**/*.md", base: "./src/content/blog" }),
  schema: z.object({
    title: z.string(),
    description: z.string(),
    pubDate: z.coerce.date(),
    updatedDate: z.coerce.date().optional(),
    author: z.string().default("Team"),
    image: z.string().optional(),
    imageAlt: z.string().optional(),
    tags: z.array(z.string()).default([]),
    draft: z.boolean().default(false),
  }),
});

const cities = defineCollection({
  loader: glob({ pattern: "**/*.yaml", base: "./src/content/cities" }),
  schema: z.object({
    slug: z.string(),
    name: z.string(),
    region: z.string(),
    title: z.string(),
    description: z.string(),
    heroBadge: z.string(),
    heroTitle: z.string(),
    heroHighlight: z.string(),
    heroSubtitle: z.string(),
    distance: z.string().optional(),
    deliveryTime: z.string(),
    deliveryNote: z.string(),
    entityStatement: z.string(),
    whatsappMessage: z.string(),
    areas: z.array(z.string()),
    areasExtra: z.string().optional(),
    pricingSectionTitle: z.string(),
    pricingSectionSubtitle: z.string(),
    servicesSectionTitle: z.string(),
    servicesSectionSubtitle: z.string(),
    buyDescription: z.string(),
    rentDescription: z.string(),
    deliveryDescription: z.string(),
    quoteSectionTitle: z.string(),
    quoteSectionSubtitle: z.string(),
    faqSectionTitle: z.string(),
    faqSectionSubtitle: z.string(),
    ctaTitle: z.string(),
    ctaDescription: z.string(),
    useCases: z.array(z.object({
      title: z.string(),
      description: z.string(),
    })).optional(),
    faqs: z.array(z.object({
      question: z.string(),
      answer: z.string(),
    })),
  }),
});

const products = defineCollection({
  loader: glob({ pattern: "**/*.yaml", base: "./src/content/products" }),
  schema: z.object({
    slug: z.string(),
    title: z.string(),
    description: z.string(),
    size: z.string(),
    whatsappMessage: z.string(),
    heroImageAlt: z.string(),
    dimensions: z.object({
      external: z.string(),
      internal: z.string(),
      doorOpening: z.string(),
    }),
    capacity: z.object({
      cubicFeet: z.string(),
      weight: z.string(),
      maxPayload: z.string(),
    }),
    features: z.array(z.string()),
    useCases: z.array(z.object({
      title: z.string(),
      description: z.string(),
    })),
    ctaTitle: z.string(),
    ctaDescription: z.string(),
    faqs: z.array(z.object({
      question: z.string(),
      answer: z.string(),
    })),
  }),
});

export const collections = { blog, cities, products };
