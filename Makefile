start:
	docker compose up -d

stop:
	docker compose down

shell:
	docker exec -it solicms-app bash

deploy:
	cd deployment && ansible-playbook -i hosts.ini deploy.yml
