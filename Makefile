start:
	docker compose up -d

stop:
	docker compose down

deploy:
	cd deployment && ansible-playbook -i hosts.ini deploy.yml
