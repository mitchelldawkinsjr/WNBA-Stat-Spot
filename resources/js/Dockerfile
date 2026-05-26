FROM node:20-alpine

WORKDIR /app

# Install dependencies first (better caching)
COPY package*.json ./
RUN npm install

# Copy the rest of the application
COPY . .

# Build the application
RUN npm run build

EXPOSE 4173

# Set environment variables
ENV PORT=4173
ENV HOST=0.0.0.0

# Use the production build with node adapter and environment variables
CMD ["sh", "-c", "PORT=${PORT:-4173} HOST=${HOST:-0.0.0.0} node build"]
