# see docker-compose.yaml to get started
#
# alternatively you can build manually with:
#
# docker build -t ee_npc .
#
# you can drop into a shell with this:
#
# docker run -it ee_npc /bin/bash
#
# inside the container you can run the bot script with:
#
# ./ee_npc
#


FROM php:7.2-cli

WORKDIR /ee_npc
COPY . .

ENTRYPOINT docker/entrypoint.sh
