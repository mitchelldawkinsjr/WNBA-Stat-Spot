FROM node:20-alpine

WORKDIR /app

# Install dependencies first (better caching)
COPY package*.json ./
RUN npm install
RUN npm install @sveltejs/adapter-node --save-dev

# Copy the rest of the application
COPY . .

# Build the application
RUN npm run build

EXPOSE 4173

# Use the production build
CMD ["npm", "run", "preview", "--", "--host"]
